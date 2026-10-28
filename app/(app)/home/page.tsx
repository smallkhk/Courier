import Link from "next/link";
import { Avatar } from "@/components/Avatar";
import { PhoneIcon, PlusIcon } from "@/components/Icons";
import { config, formatUsd, minutesFor } from "@/lib/config.ts";
import { pageUser } from "@/lib/page-auth.ts";
import { getCredit, getProfile, listCallers, listCalls } from "@/lib/repo.ts";

function ago(ts: number): string {
  const s = Math.floor(Date.now() / 1000) - ts;
  if (s < 3600) return `${Math.max(1, Math.floor(s / 60))}m ago`;
  if (s < 86400) return `${Math.floor(s / 3600)}h ago`;
  return `${Math.floor(s / 86400)}d ago`;
}

export default async function Home() {
  const user = await pageUser();
  const profile = getProfile(user.accountId);
  const callers = listCallers(user.accountId);
  const calls = listCalls(user.accountId, { limit: 8 }).filter((c) => c.status === "done" || c.status === "failed");
  const credit = getCredit(user.accountId);
  const names = new Map(callers.map((c) => [c.id, c.name]));
  const firstName = profile.full_name.split(" ")[0];
  const hour = new Date().getHours();
  const greeting = hour < 12 ? "Good morning" : hour < 17 ? "Good afternoon" : "Good evening";

  return (
    <>
      <p className="muted" style={{ marginBottom: 2 }}>{greeting}</p>
      <h1>{firstName ? `${firstName} 👋` : "Welcome 👋"}</h1>

      {!profile.full_name && (
        <div className="callout">
          <span style={{ fontSize: "1.4rem" }}>✨</span>
          <div style={{ flex: 1 }}>
            <strong>Tell your AI who you are</strong>
            <div className="small muted">It needs your name and a bit about you before it can speak for you.</div>
          </div>
          <Link className="button" href="/profile">
            Set up
          </Link>
        </div>
      )}

      <section className="card card-glow balance-card">
        <div className="row spread" style={{ alignItems: "flex-end" }}>
          <div>
            <div className="muted small">Balance</div>
            <div className="balance-amount">{formatUsd(credit)}</div>
            <div className="stat-row">
              <div className="stat">
                <strong>{minutesFor(credit)} min</strong>
                <span className="small muted">talk time left</span>
              </div>
              <div className="stat">
                <strong>{formatUsd(config.pricePerMinuteMicros)}</strong>
                <span className="small muted">per minute</span>
              </div>
            </div>
          </div>
          <Link className="button" href="/wallet">
            <PlusIcon size={16} /> Top up
          </Link>
        </div>
      </section>

      <div className="section-head">
        <h2>My callers</h2>
        {callers.length > 0 && (
          <Link className="button secondary" href="/callers/new" style={{ padding: "8px 14px", fontSize: "0.85rem" }}>
            <PlusIcon size={15} /> Add
          </Link>
        )}
      </div>
      <div className="callers">
        {callers.map((c) => (
          <Link key={c.id} href={`/callers/${c.id}`} className="caller-card">
            <div className="row" style={{ flexWrap: "nowrap" }}>
              <Avatar name={c.name} />
              <div style={{ minWidth: 0 }}>
                <strong>{c.name}</strong>
                <div className="small muted">{c.relationship || "Caller"}</div>
              </div>
            </div>
            <span className="button call-cta">
              <PhoneIcon size={14} /> Call
            </span>
          </Link>
        ))}
        <Link href="/callers/new" className="caller-card add-card">
          <span className="avatar" style={{ background: "var(--surface-2)", color: "var(--text)" }}>
            <PlusIcon />
          </span>
          <div>
            <strong style={{ color: "var(--text)" }}>Add a caller</strong>
            <div className="small">Brother, mum, a client…</div>
          </div>
        </Link>
      </div>

      <div className="section-head">
        <h2>Recent calls</h2>
      </div>
      <section className="card" style={{ marginTop: 10 }}>
        {calls.length === 0 ? (
          <div className="empty">
            <span className="emoji">📞</span>
            Your calls and their summaries will show up here.
          </div>
        ) : (
          <ul className="timeline">
            {calls.map((c) => {
              const name = (c.caller_id && names.get(c.caller_id)) || "Deleted caller";
              return (
                <li key={c.id}>
                  <Avatar name={name} />
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div className="row spread">
                      <strong>{c.caller_id && names.get(c.caller_id) ? <Link href={`/callers/${c.caller_id}`} style={{ color: "var(--text)" }}>{name}</Link> : name}</strong>
                      <span className="small faint">
                        {ago(c.created_at)} · {Math.max(1, Math.ceil(c.duration_secs / 60))} min
                      </span>
                    </div>
                    {c.summary && <p className="small muted" style={{ margin: "4px 0 0" }}>{c.summary}</p>}
                  </div>
                </li>
              );
            })}
          </ul>
        )}
      </section>
    </>
  );
}
