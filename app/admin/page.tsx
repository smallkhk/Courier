import Link from "next/link";
import { BarChart } from "@/components/admin/BarChart";
import { getStats } from "@/lib/admin.ts";
import { config, formatUsd } from "@/lib/config.ts";

function Stat({ label, value, sub, alert, href }: { label: string; value: string; sub?: string; alert?: boolean; href?: string }) {
  const body = (
    <div className={`stat-card ${alert ? "alert" : ""}`}>
      <div className="label">{label}</div>
      <div className="value">{value}</div>
      {sub && <div className="sub">{sub}</div>}
    </div>
  );
  return href ? (
    <Link href={href} style={{ color: "inherit" }}>
      {body}
    </Link>
  ) : (
    body
  );
}

export default function AdminOverview() {
  const s = getStats();
  const profit = s.callRevenueMicros - s.estCostMicros;
  const short = (day: string) => new Date(`${day}T00:00:00Z`).toLocaleDateString("en-GB", { day: "numeric", month: "short", timeZone: "UTC" });
  return (
    <>
      <h1>Overview</h1>
      <p className="muted">Everything happening on {config.platformName}.</p>

      {(s.reviewTopups > 0 || !config.callsEnabled || !config.topupsEnabled || !config.signupsOpen || !config.changerEnabled) && (
        <div className="callout">
          <span style={{ fontSize: "1.3rem" }}>⚠️</span>
          <div style={{ flex: 1 }} className="small">
            {s.reviewTopups > 0 && (
              <div>
                <strong>{s.reviewTopups} top-up{s.reviewTopups > 1 ? "s" : ""} waiting for review.</strong>{" "}
                <Link href="/admin/payments?status=review">Review now</Link>
              </div>
            )}
            {!config.callsEnabled && <div>Calls are switched off (maintenance mode).</div>}
            {!config.topupsEnabled && <div>Top-ups are switched off.</div>}
            {!config.signupsOpen && <div>New sign-ups are closed.</div>}
            {!config.changerEnabled && <div>The voice changer is switched off.</div>}
          </div>
        </div>
      )}

      <div className="stat-grid">
        <Stat label="Users" value={String(s.users)} sub={`+${s.newUsers7d} this week · ${s.blocked} blocked`} href="/admin/users" />
        <Stat label="USDT received" value={formatUsd(s.depositedMicros)} sub={`${formatUsd(s.deposited7dMicros)} this week`} href="/admin/payments" />
        <Stat label="Call revenue" value={formatUsd(s.callRevenueMicros)} sub={`${Math.round(s.callSeconds / 60)} minutes billed`} />
        <Stat label="Est. profit on calls" value={formatUsd(profit)} sub={`after ~${formatUsd(s.estCostMicros)} ElevenLabs cost`} />
        <Stat label="Calls" value={String(s.calls)} sub={`${s.calls7d} this week`} href="/admin/calls" />
        <Stat label="Unused balances" value={formatUsd(s.balancesMicros)} sub="Credit customers still hold" />
        <Stat label="Top-ups waiting" value={String(s.openTopups)} sub="Open, not paid yet" href="/admin/payments?status=pending" />
        <Stat label="Needs review" value={String(s.reviewTopups)} alert={s.reviewTopups > 0} sub="Amount didn't match" href="/admin/payments?status=review" />
        <Stat label="Voice changer revenue" value={formatUsd(s.changerRevenueMicros)} sub={`${Math.round(s.changerSeconds / 60)} minutes used`} />
        <Stat label="Cloned voices" value={String(s.clones)} href="/admin/voices" />
      </div>

      <div className="grid2" style={{ alignItems: "start" }}>
        <BarChart
          title="Call revenue per day"
          points={s.daily.map((d) => ({ label: short(d.day), value: d.revenue }))}
          format={formatUsd}
        />
        <BarChart
          title="USDT received per day"
          points={s.daily.map((d) => ({ label: short(d.day), value: d.deposits }))}
          format={formatUsd}
        />
      </div>
    </>
  );
}
