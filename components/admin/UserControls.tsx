"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { api } from "../api";

type Props = {
  userId: string;
  email: string;
  isAdmin: boolean;
  blocked: boolean;
  isSelf: boolean;
};

function useAction(userId: string) {
  const router = useRouter();
  const [status, setStatus] = useState<{ ok?: string; error?: string }>({});
  const [busy, setBusy] = useState(false);
  async function run(body: object, ok: string, after?: () => void) {
    setBusy(true);
    setStatus({});
    try {
      await api(`/api/admin/users/${userId}`, { method: "POST", body: JSON.stringify(body) });
      setStatus({ ok });
      after?.();
      router.refresh();
    } catch (err) {
      setStatus({ error: (err as Error).message });
    } finally {
      setBusy(false);
    }
  }
  return { run, status, busy };
}

function Status({ status }: { status: { ok?: string; error?: string } }) {
  return (
    <>
      {status.error && <p className="error small">{status.error}</p>}
      {status.ok && <p className="ok small">{status.ok}</p>}
    </>
  );
}

export function UserControls({ userId, email, isAdmin, blocked, isSelf }: Props) {
  const router = useRouter();
  const credit = useAction(userId);
  const access = useAction(userId);
  const password = useAction(userId);
  const danger = useAction(userId);
  const [amount, setAmount] = useState("");
  const [reason, setReason] = useState("");
  const [blockReason, setBlockReason] = useState("");
  const [newPassword, setNewPassword] = useState("");

  return (
    <>
      <section className="card stack">
        <h2>Balance</h2>
        <div className="grid2">
          <div>
            <label htmlFor="amount">Amount in USD (negative to remove)</label>
            <input id="amount" type="number" step="0.01" value={amount} onChange={(e) => setAmount(e.target.value)} placeholder="e.g. 5 or -2.50" />
          </div>
          <div>
            <label htmlFor="reason">Reason (shown in their history)</label>
            <input id="reason" value={reason} onChange={(e) => setReason(e.target.value)} maxLength={200} placeholder="e.g. Refund for dropped call" />
          </div>
        </div>
        <Status status={credit.status} />
        <div className="row">
          <button
            disabled={credit.busy || !Number(amount)}
            onClick={() =>
              credit.run({ action: "credit", amount: Number(amount), reason }, Number(amount) > 0 ? "Credit added." : "Credit removed.", () => {
                setAmount("");
                setReason("");
              })
            }
          >
            {Number(amount) < 0 ? "Remove credit" : "Add credit"}
          </button>
          {[1, 5, 10].map((n) => (
            <button key={n} className="secondary" type="button" onClick={() => setAmount(String(n))}>
              +${n}
            </button>
          ))}
        </div>
      </section>

      <section className="card stack">
        <h2>Access</h2>
        <Status status={access.status} />
        {!isSelf &&
          (blocked ? (
            <button className="secondary" disabled={access.busy} onClick={() => access.run({ action: "unblock" }, "Account unblocked.")}>
              Unblock account
            </button>
          ) : (
            <div className="row" style={{ alignItems: "flex-end" }}>
              <div style={{ flex: 1, minWidth: 220 }}>
                <label htmlFor="blockReason">Block reason (the user sees this)</label>
                <input id="blockReason" value={blockReason} onChange={(e) => setBlockReason(e.target.value)} maxLength={200} placeholder="e.g. Abuse of service" />
              </div>
              <button
                className="danger"
                disabled={access.busy}
                onClick={() => {
                  if (confirm(`Block ${email}? They will be signed out and can't sign in.`)) {
                    access.run({ action: "block", reason: blockReason }, "Account blocked and signed out.");
                  }
                }}
              >
                Block account
              </button>
            </div>
          ))}
        <div className="row">
          {isAdmin ? (
            !isSelf && (
              <button className="secondary" disabled={access.busy} onClick={() => access.run({ action: "remove_admin" }, "Admin access removed.")}>
                Remove admin access
              </button>
            )
          ) : (
            <button
              className="secondary"
              disabled={access.busy}
              onClick={() => {
                if (confirm(`Make ${email} an admin? They will have full control of the site.`)) {
                  access.run({ action: "make_admin" }, "Now an admin.");
                }
              }}
            >
              Make admin
            </button>
          )}
          <button className="secondary" disabled={access.busy} onClick={() => access.run({ action: "sign_out" }, "Signed out of all devices.")}>
            Sign out everywhere
          </button>
        </div>
      </section>

      <section className="card stack">
        <h2>Password</h2>
        <div>
          <label htmlFor="newPassword">Set a new password (signs them out everywhere)</label>
          <input id="newPassword" type="text" autoComplete="off" value={newPassword} onChange={(e) => setNewPassword(e.target.value)} minLength={10} placeholder="At least 10 characters" />
        </div>
        <Status status={password.status} />
        <div>
          <button
            className="secondary"
            disabled={password.busy || newPassword.length < 10}
            onClick={() => password.run({ action: "set_password", password: newPassword }, "Password changed. Send it to them securely.", () => setNewPassword(""))}
          >
            Set password
          </button>
        </div>
      </section>

      {!isSelf && (
        <section className="card stack danger-zone">
          <h2>Delete account</h2>
          <p className="small muted">
            Deletes the user, their callers, calls, top-up history and balance, and their cloned voice on ElevenLabs. This can&apos;t be undone.
          </p>
          <Status status={danger.status} />
          <div>
            <button
              className="danger"
              disabled={danger.busy}
              onClick={async () => {
                const typed = prompt(`Type DELETE to permanently delete ${email}`);
                if (typed !== "DELETE") return;
                await danger.run({ action: "delete", confirm: "DELETE" }, "Deleted.", () => router.push("/admin/users"));
              }}
            >
              Delete account permanently
            </button>
          </div>
        </section>
      )}
    </>
  );
}
