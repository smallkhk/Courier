// Reads incoming USDT transfers to our wallets straight from the chains.
// TRC20 uses the TronGrid REST API; BEP20 uses any BNB Smart Chain JSON-RPC
// node. Only confirmed transfers are returned.
import { config } from "./config.ts";
import { kvGet, kvSet } from "./db.ts";

export type Network = "trc20" | "bep20";

export type IncomingTransfer = {
  network: Network;
  txHash: string;
  from: string;
  amountMicros: number;
  timestamp: number; // unix seconds
};

export class ChainError extends Error {}

// --------------------------------------------------------------------- TRON

const TRON_USDT = "TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t"; // 6 decimals
const TRONGRID = process.env.TRONGRID_URL || "https://api.trongrid.io";

type TronTransfer = {
  transaction_id: string;
  from: string;
  to: string;
  value: string;
  block_timestamp: number;
  type: string;
  token_info: { address: string; decimals: number };
};

export async function tronIncoming(sinceSecs: number, maxPages = 5): Promise<IncomingTransfer[]> {
  const wallet = config.wallets.trc20;
  if (!wallet) return [];
  const out: IncomingTransfer[] = [];
  let fingerprint: string | undefined;
  for (let page = 0; page < maxPages; page++) {
    const query = new URLSearchParams({
      only_confirmed: "true",
      only_to: "true",
      limit: "200",
      contract_address: TRON_USDT,
      min_timestamp: String(sinceSecs * 1000),
    });
    if (fingerprint) query.set("fingerprint", fingerprint);
    const headers: Record<string, string> = {};
    if (process.env.TRONGRID_API_KEY) headers["TRON-PRO-API-KEY"] = process.env.TRONGRID_API_KEY;
    const response = await fetch(`${TRONGRID}/v1/accounts/${wallet}/transactions/trc20?${query}`, {
      headers,
      cache: "no-store",
    });
    if (!response.ok) throw new ChainError(`TronGrid ${response.status}`);
    const body = (await response.json()) as { data: TronTransfer[]; meta?: { fingerprint?: string } };
    for (const t of body.data) {
      if (t.type !== "Transfer" || t.to !== wallet || t.token_info?.address !== TRON_USDT) continue;
      if (t.token_info.decimals !== 6) continue;
      out.push({
        network: "trc20",
        txHash: t.transaction_id.toLowerCase(),
        from: t.from,
        amountMicros: Number(BigInt(t.value)),
        timestamp: Math.floor(t.block_timestamp / 1000),
      });
    }
    fingerprint = body.meta?.fingerprint;
    if (!fingerprint) break;
  }
  return out;
}

// ---------------------------------------------------------------- BNB chain

const BSC_USDT = "0x55d398326f99059ff775485246999027b3197955"; // 18 decimals
const TRANSFER_TOPIC = "0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef";
const BSC_RPC = process.env.BSC_RPC_URL || "https://bsc-rpc.publicnode.com";
const BSC_CONFIRMATIONS = 15;
const WEI_PER_MICRO = 10n ** 12n;

let rpcId = 0;

async function rpc<T>(method: string, params: unknown[]): Promise<T> {
  const response = await fetch(BSC_RPC, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ jsonrpc: "2.0", id: ++rpcId, method, params }),
    cache: "no-store",
  });
  if (!response.ok) throw new ChainError(`BSC RPC ${response.status}`);
  const body = (await response.json()) as { result?: T; error?: { message: string } };
  if (body.error) throw new ChainError(`BSC RPC ${method}: ${body.error.message}`);
  return body.result as T;
}

type Log = { transactionHash: string; blockNumber: string; data: string; topics: string[]; address: string };

function padAddress(address: string): string {
  return `0x${address.toLowerCase().replace(/^0x/, "").padStart(64, "0")}`;
}

function topicToAddress(topic: string): string {
  return `0x${topic.slice(-40)}`.toLowerCase();
}

// Converts an 18-decimal USDT amount to micro-units. Amounts with dust below
// 6 decimals can't match an invoice exactly, so they return null.
export function bscAmountToMicros(data: string): number | null {
  const wei = BigInt(data);
  if (wei % WEI_PER_MICRO !== 0n) return null;
  return Number(wei / WEI_PER_MICRO);
}

function isOurUsdtTransfer(log: Log): boolean {
  return (
    log.address.toLowerCase() === BSC_USDT &&
    log.topics[0]?.toLowerCase() === TRANSFER_TOPIC &&
    log.topics[2] !== undefined &&
    topicToAddress(log.topics[2]) === config.wallets.bep20
  );
}

const blockTimes = new Map<string, number>();

async function blockTime(blockNumberHex: string): Promise<number> {
  const cached = blockTimes.get(blockNumberHex);
  if (cached) return cached;
  const block = await rpc<{ timestamp: string }>("eth_getBlockByNumber", [blockNumberHex, false]);
  const ts = Number(BigInt(block.timestamp));
  blockTimes.set(blockNumberHex, ts);
  return ts;
}

async function logToTransfer(log: Log, wanted?: Set<number>): Promise<IncomingTransfer | null> {
  const amountMicros = bscAmountToMicros(log.data);
  if (amountMicros === null) return null;
  // Skip the block-time lookup for amounts no open top-up is waiting for.
  if (wanted && !wanted.has(amountMicros)) return null;
  return {
    network: "bep20",
    txHash: log.transactionHash.toLowerCase(),
    from: log.topics[1] ? topicToAddress(log.topics[1]) : "",
    amountMicros,
    timestamp: await blockTime(log.blockNumber),
  };
}

// Scans new blocks since the last scan (or about the last hour on first run)
// for USDT transfers to our wallet. Public BSC nodes often limit eth_getLogs;
// set BSC_RPC_URL to a provider node for reliable automatic detection.
export async function bscIncoming(wanted?: Set<number>): Promise<IncomingTransfer[]> {
  if (!config.wallets.bep20) return [];
  const head = Number(BigInt(await rpc<string>("eth_blockNumber", [])));
  const safeHead = head - BSC_CONFIRMATIONS;
  const lastScanned = Number(kvGet("bsc_last_block") ?? 0);
  // BSC makes a block roughly every 0.75 seconds; 6000 blocks is about 75 min.
  let from = Math.max(lastScanned + 1, safeHead - 6000);
  const out: IncomingTransfer[] = [];
  while (from <= safeHead) {
    const to = Math.min(from + 999, safeHead);
    const logs = await rpc<Log[]>("eth_getLogs", [
      {
        fromBlock: `0x${from.toString(16)}`,
        toBlock: `0x${to.toString(16)}`,
        address: BSC_USDT,
        topics: [TRANSFER_TOPIC, null, padAddress(config.wallets.bep20)],
      },
    ]);
    for (const log of logs) {
      if (!isOurUsdtTransfer(log)) continue;
      const transfer = await logToTransfer(log, wanted);
      if (transfer) out.push(transfer);
    }
    kvSet("bsc_last_block", String(to));
    from = to + 1;
  }
  return out;
}

// Looks up one transaction by hash (used when a customer pastes their hash).
export async function bscTransferByHash(txHash: string): Promise<IncomingTransfer | null> {
  if (!config.wallets.bep20) return null;
  const receipt = await rpc<{ status: string; blockNumber: string; logs: Log[] } | null>(
    "eth_getTransactionReceipt",
    [txHash],
  );
  if (!receipt || receipt.status !== "0x1") return null;
  const head = Number(BigInt(await rpc<string>("eth_blockNumber", [])));
  if (head - Number(BigInt(receipt.blockNumber)) < BSC_CONFIRMATIONS) return null;
  const log = receipt.logs.find(isOurUsdtTransfer);
  return log ? logToTransfer(log) : null;
}

export async function tronTransferByHash(txHash: string, sinceSecs: number): Promise<IncomingTransfer | null> {
  const transfers = await tronIncoming(sinceSecs);
  return transfers.find((t) => t.txHash === txHash) ?? null;
}

export function normalizeHash(network: Network, raw: string): string | null {
  const hash = raw.trim().toLowerCase();
  if (network === "trc20") return /^(0x)?[0-9a-f]{64}$/.test(hash) ? hash.replace(/^0x/, "") : null;
  return /^0x[0-9a-f]{64}$/.test(hash) ? hash : /^[0-9a-f]{64}$/.test(hash) ? `0x${hash}` : null;
}
