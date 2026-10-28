import Link from "next/link";
import { DepositTable } from "@/components/admin/DepositTable";
import { listAllDeposits } from "@/lib/admin.ts";
import { config } from "@/lib/config.ts";

const FILTERS = [
  { key: "", label: "All" },
  { key: "review", label: "Needs review" },
  { key: "pending", label: "Waiting" },
  { key: "credited", label: "Credited" },
  { key: "expired", label: "Expired" },
  { key: "rejected", label: "Rejected" },
];

export default async function AdminPayments({ searchParams }: { searchParams: Promise<{ status?: string }> }) {
  const { status = "" } = await searchParams;
  const deposits = listAllDeposits(FILTERS.some((f) => f.key === status) ? status || undefined : undefined);
  const { trc20, bep20 } = config.wallets;
  return (
    <>
      <h1>Payments</h1>
      <p className="muted">
        USDT top-ups. Hashes link to Tronscan or BscScan so you can check a payment before approving it.
      </p>
      <div className="callout" style={{ background: "var(--surface)", borderColor: "var(--border)" }}>
        <div className="small" style={{ flex: 1 }}>
          <div>
            <strong>TRC20 wallet:</strong> <span className="mono">{trc20 || "not set (off)"}</span>
          </div>
          <div>
            <strong>BEP20 wallet:</strong> <span className="mono">{bep20 || "not set (off)"}</span>
          </div>
        </div>
        <Link className="button secondary" href="/admin/settings">
          Change
        </Link>
      </div>
      <div className="filter-chips" style={{ marginTop: 16 }}>
        {FILTERS.map((f) => (
          <Link key={f.key} href={`/admin/payments${f.key ? `?status=${f.key}` : ""}`} className={status === f.key ? "active" : ""}>
            {f.label}
          </Link>
        ))}
      </div>
      <section className="card">
        <DepositTable deposits={deposits} />
      </section>
    </>
  );
}
