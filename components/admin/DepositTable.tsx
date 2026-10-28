import Link from "next/link";
import { MICROS_PER_USD } from "@/lib/config.ts";
import type { Deposit } from "@/lib/repo.ts";
import { DepositActions } from "./DepositActions";

const PILL: Record<string, string> = { pending: "amber", review: "amber", credited: "green", expired: "", rejected: "red" };
const LABEL: Record<string, string> = { pending: "Waiting", review: "Needs review", credited: "Credited", expired: "Expired", rejected: "Rejected" };

function explorer(network: string, hash: string): string {
  return network === "trc20" ? `https://tronscan.org/#/transaction/${hash}` : `https://bscscan.com/tx/${hash}`;
}

export function DepositTable({ deposits }: { deposits: (Deposit & { email?: string; user_id?: string })[] }) {
  if (deposits.length === 0) return <div className="empty">No top-ups.</div>;
  return (
    <div className="table-wrap">
      <table className="admin-table">
        <thead>
          <tr>
            <th>When</th>
            {deposits[0].email !== undefined && <th>User</th>}
            <th>Amount</th>
            <th>Status</th>
            <th>Transaction</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          {deposits.map((d) => (
            <tr key={d.id}>
              <td>{new Date(d.created_at * 1000).toLocaleString()}</td>
              {d.email !== undefined && (
                <td>
                  <Link href={`/admin/users/${d.user_id}`}>{d.email}</Link>
                </td>
              )}
              <td>
                <strong>{(d.amount_micros / MICROS_PER_USD).toFixed(2)}</strong> USDT · {d.network.toUpperCase()}
              </td>
              <td className="wrap">
                <span className={`pill ${PILL[d.status] ?? ""}`}>{LABEL[d.status] ?? d.status}</span>
                {d.note && <div className="small muted" style={{ marginTop: 4 }}>{d.note}</div>}
              </td>
              <td>
                {d.tx_hash ? (
                  <a className="mono small" href={explorer(d.network, d.tx_hash)} target="_blank" rel="noreferrer">
                    {d.tx_hash.slice(0, 10)}…{d.tx_hash.slice(-6)} ↗
                  </a>
                ) : (
                  <span className="faint small">—</span>
                )}
              </td>
              <td>
                <DepositActions id={d.id} status={d.status} amount={d.amount_micros / MICROS_PER_USD} />
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
