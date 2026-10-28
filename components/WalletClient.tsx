"use client";

import QRCode from "qrcode";
import { useRouter } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import type { Deposit } from "@/lib/repo.ts";
import { api, usd } from "./api";
import { CheckIcon, CopyIcon } from "./Icons";

const NETWORK_LABEL: Record<Deposit["network"], string> = {
  trc20: "USDT on TRON (TRC20)",
  bep20: "USDT on BNB Smart Chain (BEP20)",
};

const NETWORK_SHORT: Record<Deposit["network"], { name: string; note: string }> = {
  trc20: { name: "TRC20", note: "TRON network" },
  bep20: { name: "BEP20", note: "BNB Smart Chain" },
};

const STATUS_PILL: Record<Deposit["status"], string> = {
  pending: "amber",
  credited: "green",
  expired: "",
  review: "amber",
};

const QUICK_AMOUNTS = [5, 10, 20, 50];

const STATUS_LABEL: Record<Deposit["status"], string> = {
  pending: "Waiting for payment",
  credited: "Credited",
  expired: "Expired",
  review: "Being checked by support",
};

function usdt(micros: number): string {
  return (micros / 1_000_000).toFixed(2);
}

function CopyField({ value, label }: { value: string; label: string }) {
  const [copied, setCopied] = useState(false);
  return (
    <div>
      <label>{label}</label>
      <div className="copy">
        <code className="mono">{value}</code>
        <button
          type="button"
          className="secondary"
          onClick={async () => {
            await navigator.clipboard.writeText(value);
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
          }}
        >
          {copied ? <CheckIcon size={16} /> : <CopyIcon size={16} />}
          {copied ? "Copied" : "Copy"}
        </button>
      </div>
    </div>
  );
}

export function WalletClient({
  wallets,
  networks,
  minDeposit,
  initialDeposits,
  initialCredit,
}: {
  wallets: Record<Deposit["network"], string>;
  networks: Deposit["network"][];
  minDeposit: number;
  initialDeposits: Deposit[];
  initialCredit: number;
}) {
  const router = useRouter();
  const [network, setNetwork] = useState<Deposit["network"]>(networks[0] ?? "trc20");
  const [amount, setAmount] = useState(String(Math.max(minDeposit, 10)));
  const [deposits, setDeposits] = useState(initialDeposits);
  const [credit, setCredit] = useState(initialCredit);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const open = deposits.find((d) => d.status === "pending" || d.status === "review");

  async function create(event: React.FormEvent) {
    event.preventDefault();
    setBusy(true);
    setError("");
    try {
      const { deposit } = await api<{ deposit: Deposit }>("/api/wallet/deposits", {
        method: "POST",
        body: JSON.stringify({ network, amount: Number(amount) }),
      });
      setDeposits([deposit, ...deposits]);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  const update = useCallback(
    (d: Deposit, newCredit: number) => {
      setDeposits((all) => all.map((x) => (x.id === d.id ? d : x)));
      setCredit(newCredit);
      if (d.status === "credited") router.refresh();
    },
    [router],
  );

  if (networks.length === 0) {
    return <p className="card muted">Crypto top-ups aren&apos;t set up yet. The admin needs to add the USDT wallet addresses.</p>;
  }

  return (
    <>
      <section className="card card-glow">
        <div className="muted small">Balance</div>
        <div className="balance-amount">{usd(credit)}</div>
        <div className="small muted" style={{ marginTop: 6 }}>1 USDT = $1 of call credit</div>
      </section>

      {open ? (
        <OpenDeposit deposit={open} address={wallets[open.network]} onUpdate={update} />
      ) : (
        <form className="card stack" onSubmit={create}>
          <h2>Top up with USDT</h2>
          <div>
            <label>Network</label>
            <div className="net-toggle">
              {networks.map((n) => (
                <button key={n} type="button" className={network === n ? "active" : ""} onClick={() => setNetwork(n)}>
                  {NETWORK_SHORT[n].name}
                  <small>{NETWORK_SHORT[n].note}</small>
                </button>
              ))}
            </div>
          </div>
          <div className="stack">
            <div>
              <label htmlFor="amount">Amount (USDT)</label>
              <input
                id="amount"
                type="number"
                min={minDeposit}
                max={10000}
                step={1}
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
                required
              />
            </div>
            <div className="amount-chips">
              {QUICK_AMOUNTS.filter((a) => a >= minDeposit).map((a) => (
                <button
                  key={a}
                  type="button"
                  className={Number(amount) === a ? "" : "secondary"}
                  onClick={() => setAmount(String(a))}
                >
                  {a} USDT
                </button>
              ))}
            </div>
          </div>
          <p className="small muted">Minimum {minDeposit} USDT. Credit lands automatically once the payment confirms.</p>
          {error && <p className="error">{error}</p>}
          <button type="submit" disabled={busy} className="big-btn" style={{ width: "100%" }}>
            Continue to payment
          </button>
        </form>
      )}

      <section className="card">
        <h2>Top-up history</h2>
        {deposits.length === 0 ? (
          <div className="empty">
            <span className="emoji">💸</span>
            No top-ups yet.
          </div>
        ) : (
          <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>When</th>
                <th>Amount</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {deposits.map((d) => (
                <tr key={d.id}>
                  <td>{new Date(d.created_at * 1000).toLocaleString()}</td>
                  <td>
                    {usdt(d.amount_micros)} USDT · {d.network.toUpperCase()}
                  </td>
                  <td>
                    <span className={`pill ${STATUS_PILL[d.status]}`}>{STATUS_LABEL[d.status]}</span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          </div>
        )}
      </section>
    </>
  );
}

function OpenDeposit({
  deposit,
  address,
  onUpdate,
}: {
  deposit: Deposit;
  address: string;
  onUpdate: (d: Deposit, credit: number) => void;
}) {
  const [qr, setQr] = useState("");
  const [hash, setHash] = useState("");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const [left, setLeft] = useState(deposit.expires_at - Math.floor(Date.now() / 1000));

  useEffect(() => {
    QRCode.toDataURL(address, { margin: 0, width: 360 }).then(setQr).catch(() => setQr(""));
  }, [address]);

  // Watch for the payment: the server scans the chain every ~15 seconds.
  useEffect(() => {
    if (deposit.status !== "pending") return;
    const poll = setInterval(async () => {
      try {
        const r = await api<{ deposit: Deposit; creditMicros: number }>(`/api/wallet/deposits/${deposit.id}`);
        onUpdate(r.deposit, r.creditMicros);
      } catch {
        // Keep polling.
      }
    }, 10_000);
    const tick = setInterval(() => setLeft(deposit.expires_at - Math.floor(Date.now() / 1000)), 1000);
    return () => {
      clearInterval(poll);
      clearInterval(tick);
    };
  }, [deposit.id, deposit.status, deposit.expires_at, onUpdate]);

  async function claim(event: React.FormEvent) {
    event.preventDefault();
    setBusy(true);
    setError("");
    try {
      const r = await api<{ deposit: Deposit; creditMicros: number }>(`/api/wallet/deposits/${deposit.id}`, {
        method: "POST",
        body: JSON.stringify({ hash }),
      });
      onUpdate(r.deposit, r.creditMicros);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  if (deposit.status === "review") {
    return (
      <section className="card">
        <h2>Payment being checked</h2>
        <p>{deposit.note}</p>
        <p className="small muted">Support will credit it after checking. Reference: {deposit.id}</p>
      </section>
    );
  }

  return (
    <section className="card stack">
      <div className="row spread">
        <h2 style={{ margin: 0 }}>Complete your payment</h2>
        <span className="pill amber">{NETWORK_SHORT[deposit.network].name}</span>
      </div>
      <div>
        <div className="small muted">Send exactly</div>
        <div className="exact grad-text">{usdt(deposit.amount_micros)} USDT</div>
        <div className="small">
          on <strong>{NETWORK_LABEL[deposit.network]}</strong>. Other networks lose the funds.
        </div>
      </div>
      <div className="pay-box">
        {qr && <img className="qr" src={qr} alt="Wallet address QR code" />}
        <div className="stack">
          <CopyField label="Amount (send this exact amount)" value={usdt(deposit.amount_micros)} />
          <CopyField label="To address" value={address} />
        </div>
      </div>
      <p className="small muted">
        The exact amount, including the cents, is how we know the payment is yours. If your exchange takes its fee
        out of the amount, add the fee so the address receives exactly {usdt(deposit.amount_micros)} USDT.
      </p>
      <div className="watching">
        <span className="spinner" aria-hidden />
        <div className="small">
          <strong>Watching the blockchain for your payment</strong>
          <div className="muted">
            {left > 0 ? `${Math.floor(left / 60)} min left to pay` : "Time is up, but late payments still count for a few minutes"}
          </div>
        </div>
      </div>
      <form className="stack" onSubmit={claim}>
        <div>
          <label htmlFor="hash">Already paid? Paste the transaction hash to check now</label>
          <input id="hash" className="mono" value={hash} onChange={(e) => setHash(e.target.value)} placeholder="Transaction hash / TxID" />
        </div>
        {error && <p className="error">{error}</p>}
        <div>
          <button type="submit" className="secondary" disabled={busy || hash.trim().length < 10}>
            {busy ? "Checking…" : "Check payment"}
          </button>
        </div>
      </form>
    </section>
  );
}
