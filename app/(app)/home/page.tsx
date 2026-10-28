import Link from "next/link";
import { config, formatUsd, minutesFor } from "@/lib/config.ts";
import { pageUser } from "@/lib/page-auth.ts";
import { getCredit, getProfile, listCallers, listCalls } from "@/lib/repo.ts";

export default async function Home() {
  const user = await pageUser();
  const profile = getProfile(user.accountId);
  const callers = listCallers(user.accountId);
  const calls = listCalls(user.accountId, { limit: 8 }).filter((c) => c.status === "done" || c.status === "failed");
  const credit = getCredit(user.accountId);
  const names = new Map(callers.map((c) => [c.id, c.name]));

  return (
    <>
      <h1>Hi{profile.full_name ? `, ${profile.full_name.split(" ")[0]}` : ""}</h1>
      {!profile.full_name && (
        <p className="card">
          Start by filling in <Link href="/profile">My profile</Link>, so your AI knows who it&apos;s speaking as.
        </p>
      )}

      <section className="card row spread">
        <div>
          <div className="muted small">Balance</div>
          <div className="big">{formatUsd(credit)}</div>
          <div className="muted small">
            About {minutesFor(credit)} min of calls at {formatUsd(config.pricePerMinuteMicros)}/min
          </div>
        </div>
        <Link className="button" href="/wallet">
          Top up with USDT
        </Link>
      </section>

      <section className="card">
        <div className="row spread">
          <h2 style={{ margin: 0 }}>My callers</h2>
          <Link className="button secondary" href="/callers/new">
            Add caller
          </Link>
        </div>
        {callers.length === 0 ? (
          <p className="muted">Add the people you call: your brother, your mum, a client.</p>
        ) : (
          <ul className="list" style={{ marginTop: 8 }}>
            {callers.map((c) => (
              <li key={c.id} className="row spread">
                <div>
                  <strong>{c.name}</strong>
                  {c.relationship && <span className="muted small"> · {c.relationship}</span>}
                </div>
                <Link className="button" href={`/callers/${c.id}`}>
                  Call
                </Link>
              </li>
            ))}
          </ul>
        )}
      </section>

      <section className="card">
        <h2>Recent calls</h2>
        {calls.length === 0 ? (
          <p className="muted">No calls yet.</p>
        ) : (
          <ul className="list">
            {calls.map((c) => (
              <li key={c.id}>
                <div className="row spread">
                  <strong>
                    {c.caller_id && names.get(c.caller_id) ? (
                      <Link href={`/callers/${c.caller_id}`}>{names.get(c.caller_id)}</Link>
                    ) : (
                      "Deleted caller"
                    )}
                  </strong>
                  <span className="muted small">
                    {new Date(c.created_at * 1000).toLocaleString()} · {Math.ceil(c.duration_secs / 60)} min
                  </span>
                </div>
                {c.summary && <p className="small muted" style={{ margin: "4px 0 0" }}>{c.summary}</p>}
              </li>
            ))}
          </ul>
        )}
      </section>
    </>
  );
}
