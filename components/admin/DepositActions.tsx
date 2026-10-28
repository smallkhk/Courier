"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { api } from "../api";

// Approve / reject / mark-paid controls for one top-up.
export function DepositActions({ id, status, amount }: { id: string; status: string; amount: number }) {
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const [paid, setPaid] = useState(String(amount));
  const [hash, setHash] = useState("");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  async function run(body: object, confirmText?: string) {
    if (confirmText && !confirm(confirmText)) return;
    setBusy(true);
    setError("");
    try {
      await api(`/api/admin/deposits/${id}`, { method: "POST", body: JSON.stringify(body) });
      setOpen(false);
      router.refresh();
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  if (status === "credited" || status === "rejected") return <span className="faint small">—</span>;

  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 6, minWidth: 180 }}>
      <div className="row" style={{ gap: 6 }}>
        {status === "review" && (
          <button style={{ padding: "6px 12px", fontSize: "0.8rem" }} disabled={busy} onClick={() => run({ action: "approve" }, "Credit the amount received to this user?")}>
            Approve
          </button>
        )}
        <button className="secondary" style={{ padding: "6px 12px", fontSize: "0.8rem" }} disabled={busy} onClick={() => setOpen(!open)}>
          Mark paid
        </button>
        <button
          className="danger"
          style={{ padding: "6px 12px", fontSize: "0.8rem" }}
          disabled={busy}
          onClick={() => {
            const reason = prompt("Reason for rejecting (the user sees this)?", "Payment not found");
            if (reason !== null) void run({ action: "reject", reason });
          }}
        >
          Reject
        </button>
      </div>
      {open && (
        <div className="stack" style={{ padding: 10, borderRadius: 12, background: "rgba(0,0,0,0.3)" }}>
          <div>
            <label>Amount received (USDT)</label>
            <input type="number" step="0.01" value={paid} onChange={(e) => setPaid(e.target.value)} />
          </div>
          <div>
            <label>Transaction hash (optional)</label>
            <input className="mono" value={hash} onChange={(e) => setHash(e.target.value)} placeholder="Stops it being used twice" />
          </div>
          <button
            disabled={busy || !(Number(paid) > 0)}
            onClick={() => run({ action: "mark_paid", amount: Number(paid), hash }, `Credit ${paid} USDT to this user?`)}
          >
            Credit {paid} USDT
          </button>
        </div>
      )}
      {error && <span className="error small">{error}</span>}
    </div>
  );
}
