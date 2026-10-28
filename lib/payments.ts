// USDT top-ups on our own wallets. Each top-up request gets a unique amount
// (e.g. 10.37 USDT) so an incoming transfer can be matched to the account
// that asked for it without the customer doing anything else. Customers can
// also paste their transaction hash to have it checked straight away.
import { randomInt, randomUUID } from "node:crypto";
import {
  bscIncoming,
  ChainError,
  bscTransferByHash,
  normalizeHash,
  tronIncoming,
  tronTransferByHash,
  type IncomingTransfer,
  type Network,
} from "./chains.ts";
import { config, MICROS_PER_USD } from "./config.ts";
import { getDb, now, transaction } from "./db.ts";
import { applyCredit, getDeposit, type Deposit } from "./repo.ts";

// Late confirmations still count for this long after a request expires.
const GRACE_SECONDS = 15 * 60;
const MAX_OPEN_DEPOSITS = 3;

export class PaymentError extends Error {}

export function walletFor(network: Network): string {
  return config.wallets[network];
}

export function enabledNetworks(): Network[] {
  return (["trc20", "bep20"] as const).filter((n) => Boolean(config.wallets[n]));
}

function expireOld(): void {
  getDb()
    .prepare("UPDATE deposits SET status = 'expired' WHERE status = 'pending' AND expires_at < ?")
    .run(now() - GRACE_SECONDS);
}

export function createDeposit(accountId: string, network: Network, wholeUsd: number): Deposit {
  if (!walletFor(network)) throw new PaymentError("That network isn't available right now");
  if (!Number.isInteger(wholeUsd) || wholeUsd < config.minDepositUsd || wholeUsd > 10_000) {
    throw new PaymentError(`Enter a whole amount between ${config.minDepositUsd} and 10000 USDT`);
  }
  return transaction(() => {
    expireOld();
    const db = getDb();
    const open = db
      .prepare("SELECT COUNT(*) AS n FROM deposits WHERE account_id = ? AND status = 'pending'")
      .get(accountId) as { n: number };
    if (open.n >= MAX_OPEN_DEPOSITS) {
      throw new PaymentError("You have too many open top-ups. Pay one of them or wait for it to expire.");
    }
    // Pick cents that no other open request on this network is using.
    const taken = new Set(
      (
        db
          .prepare("SELECT amount_micros FROM deposits WHERE network = ? AND status = 'pending'")
          .all(network) as { amount_micros: number }[]
      ).map((r) => r.amount_micros),
    );
    const base = wholeUsd * MICROS_PER_USD;
    const free: number[] = [];
    for (let cents = 1; cents <= 99; cents++) {
      const amount = base + cents * 10_000;
      if (!taken.has(amount)) free.push(amount);
    }
    if (free.length === 0) throw new PaymentError("Lots of top-ups at that amount right now. Try a different amount.");
    const amount = free[randomInt(free.length)];
    const id = randomUUID();
    const ts = now();
    db.prepare(
      `INSERT INTO deposits (id, account_id, network, amount_micros, status, created_at, expires_at)
       VALUES (?, ?, ?, ?, 'pending', ?, ?)`,
    ).run(id, accountId, network, amount, ts, ts + config.depositWindowSeconds);
    return getDeposit(accountId, id)!;
  });
}

// Credits the open request this transfer pays for, if any. A transfer pays
// for a request when the network and exact amount match and it arrived
// while the request was open. Each hash is credited at most once.
export function applyTransfer(t: IncomingTransfer): { depositId: string; accountId: string } | null {
  return transaction(() => {
    const db = getDb();
    if (db.prepare("SELECT 1 FROM chain_transfers WHERE tx_hash = ?").get(t.txHash)) return null;
    const deposit = db
      .prepare(
        `SELECT id, account_id FROM deposits
         WHERE network = ? AND amount_micros = ? AND status IN ('pending', 'review', 'expired')
           AND created_at - 120 <= ? AND expires_at >= ?
         ORDER BY created_at ASC LIMIT 1`,
      )
      .get(t.network, t.amountMicros, t.timestamp, t.timestamp) as { id: string; account_id: string } | undefined;
    if (!deposit) return null;
    db.prepare(
      "INSERT INTO chain_transfers (tx_hash, network, deposit_id, amount_micros, from_address, seen_at) VALUES (?, ?, ?, ?, ?, ?)",
    ).run(t.txHash, t.network, deposit.id, t.amountMicros, t.from, now());
    db.prepare("UPDATE deposits SET status = 'credited', tx_hash = ?, credited_at = ?, note = '' WHERE id = ?").run(
      t.txHash,
      now(),
      deposit.id,
    );
    applyCredit(deposit.account_id, t.amountMicros, "deposit", `USDT ${t.network.toUpperCase()} ${t.txHash}`, 0);
    return { depositId: deposit.id, accountId: deposit.account_id };
  });
}

// Pulls recent incoming transfers on every enabled network and credits any
// that match an open request. Safe to run from several places at once.
export async function scanPayments(): Promise<{ credited: number; errors: string[] }> {
  expireOld();
  const oldestOpen = getDb()
    .prepare("SELECT MIN(created_at) AS t FROM deposits WHERE status = 'pending'")
    .get() as { t: number | null };
  if (oldestOpen.t === null) return { credited: 0, errors: [] };
  const wanted = new Set(
    (
      getDb().prepare("SELECT amount_micros FROM deposits WHERE status = 'pending'").all() as {
        amount_micros: number;
      }[]
    ).map((r) => r.amount_micros),
  );
  let credited = 0;
  const errors: string[] = [];
  const sources: [Network, () => Promise<IncomingTransfer[]>][] = [
    ["trc20", () => tronIncoming(oldestOpen.t! - 120)],
    ["bep20", () => bscIncoming(wanted)],
  ];
  for (const [network, fetchTransfers] of sources) {
    if (!walletFor(network)) continue;
    try {
      for (const transfer of await fetchTransfers()) {
        if (applyTransfer(transfer)) credited++;
      }
    } catch (err) {
      errors.push(`${network}: ${(err as Error).message}`);
    }
  }
  return { credited, errors };
}

// Checks a hash a customer pasted for their own request.
export async function claimByHash(accountId: string, depositId: string, rawHash: string): Promise<Deposit> {
  const deposit = getDeposit(accountId, depositId);
  if (!deposit) throw new PaymentError("Top-up not found");
  if (deposit.status === "credited") return deposit;
  const hash = normalizeHash(deposit.network, rawHash);
  if (!hash) throw new PaymentError("That doesn't look like a transaction hash for this network");

  const used = getDb().prepare("SELECT deposit_id FROM chain_transfers WHERE tx_hash = ?").get(hash) as
    | { deposit_id: string | null }
    | undefined;
  const submitted = getDb()
    .prepare("SELECT 1 FROM deposits WHERE tx_hash = ? AND id != ?")
    .get(hash, deposit.id);
  if (used || submitted) throw new PaymentError("That transaction has already been used");

  let transfer: IncomingTransfer | null;
  try {
    transfer =
      deposit.network === "bep20"
        ? await bscTransferByHash(hash)
        : await tronTransferByHash(hash, deposit.created_at - 120);
  } catch (err) {
    if (err instanceof ChainError) {
      throw new PaymentError("We couldn't reach the blockchain just now. Try again in a minute.");
    }
    throw err;
  }
  if (!transfer) {
    throw new PaymentError(
      "We can't see a confirmed USDT payment to our wallet with that hash yet. Wait a minute and try again.",
    );
  }
  if (transfer.amountMicros !== deposit.amount_micros) {
    // Most likely an exchange took its fee out of the amount. Park it for a
    // person to check rather than guessing which account it belongs to.
    getDb()
      .prepare("UPDATE deposits SET status = 'review', tx_hash = ?, note = ? WHERE id = ? AND account_id = ?")
      .run(
        hash,
        `Received ${(transfer.amountMicros / MICROS_PER_USD).toFixed(6)} USDT instead of the exact amount`,
        deposit.id,
        accountId,
      );
    throw new PaymentError(
      "The amount received doesn't match the exact amount requested, so support will check it by hand. Your credit will be added after review.",
    );
  }
  applyTransfer(transfer);
  return getDeposit(accountId, depositId)!;
}

// For support: credit a request that is under review with what arrived.
export function approveReview(depositId: string): void {
  transaction(() => {
    const db = getDb();
    const d = db
      .prepare("SELECT account_id, network, tx_hash, status FROM deposits WHERE id = ?")
      .get(depositId) as { account_id: string; network: string; tx_hash: string | null; status: string } | undefined;
    if (!d || d.status !== "review" || !d.tx_hash) throw new PaymentError("No deposit under review with that id");
    if (db.prepare("SELECT 1 FROM chain_transfers WHERE tx_hash = ?").get(d.tx_hash)) {
      throw new PaymentError("That transaction has already been credited");
    }
    const note = (db.prepare("SELECT note FROM deposits WHERE id = ?").get(depositId) as { note: string }).note;
    const received = Number(note.match(/Received ([\d.]+)/)?.[1]);
    if (!Number.isFinite(received) || received <= 0) throw new PaymentError("Can't read the received amount");
    const micros = Math.round(received * MICROS_PER_USD);
    db.prepare(
      "INSERT INTO chain_transfers (tx_hash, network, deposit_id, amount_micros, from_address, seen_at) VALUES (?, ?, ?, ?, '', ?)",
    ).run(d.tx_hash, d.network, depositId, micros, now());
    db.prepare("UPDATE deposits SET status = 'credited', credited_at = ? WHERE id = ?").run(now(), depositId);
    applyCredit(d.account_id, micros, "deposit", `USDT ${d.network.toUpperCase()} ${d.tx_hash} (reviewed)`, 0);
  });
}
