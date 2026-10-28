import Link from "next/link";
import { notFound } from "next/navigation";
import { Avatar } from "@/components/Avatar";
import { CallList } from "@/components/admin/CallList";
import { DepositTable } from "@/components/admin/DepositTable";
import { UserControls } from "@/components/admin/UserControls";
import { AdminButton } from "@/components/admin/AdminButton";
import { getUser, getUserExtras, listAllCalls, listUserDeposits } from "@/lib/admin.ts";
import { currentUser, isBootstrapAdmin } from "@/lib/auth.ts";
import { formatUsd } from "@/lib/config.ts";
import { languageName } from "@/lib/languages.ts";

export default async function AdminUser({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const user = getUser(id);
  if (!user) notFound();
  const me = await currentUser();
  const extras = getUserExtras(user.account_id);
  const calls = listAllCalls({ accountId: user.account_id, limit: 50 });
  const deposits = listUserDeposits(user.account_id);
  const p = extras.profile;

  return (
    <>
      <Link href="/admin/users" className="small">
        ← All users
      </Link>
      <div className="caller-head" style={{ marginTop: 10 }}>
        <Avatar name={p?.full_name || user.email} size="lg" />
        <div>
          <h1>{p?.full_name || "No name yet"}</h1>
          <div className="row" style={{ gap: 6 }}>
            <span className="small muted">{user.email}</span>
            {user.blocked_at ? <span className="pill red">Blocked</span> : <span className="pill green">Active</span>}
            {(user.is_admin || isBootstrapAdmin(user.email)) && <span className="pill amber">Admin</span>}
          </div>
        </div>
      </div>

      <div className="stat-grid">
        <div className="stat-card">
          <div className="label">Balance</div>
          <div className="value">{formatUsd(user.credit_micros)}</div>
        </div>
        <div className="stat-card">
          <div className="label">Paid in</div>
          <div className="value">{formatUsd(user.deposited_micros)}</div>
        </div>
        <div className="stat-card">
          <div className="label">Calls</div>
          <div className="value">{user.calls}</div>
        </div>
        <div className="stat-card">
          <div className="label">Signed in on</div>
          <div className="value">{extras.sessions}</div>
          <div className="sub">devices</div>
        </div>
      </div>
      {user.blocked_at && (
        <div className="callout">
          🚫 Blocked on {new Date(user.blocked_at * 1000).toLocaleString()}
          {user.block_reason && <> · {user.block_reason}</>}
        </div>
      )}
      {isBootstrapAdmin(user.email) && (
        <p className="small muted" style={{ marginTop: 10 }}>
          This email is in ADMIN_EMAILS, so it stays an admin whatever the switch below says.
        </p>
      )}

      <div className="grid2" style={{ alignItems: "start" }}>
        <div>
          <UserControls
            userId={user.user_id}
            email={user.email}
            isAdmin={Boolean(user.is_admin)}
            blocked={Boolean(user.blocked_at)}
            isSelf={me?.userId === user.user_id}
          />
        </div>
        <div>
          <section className="card">
            <h2>Profile</h2>
            {p ? (
              <dl className="kv">
                <dt>Name</dt>
                <dd>{p.full_name || "—"}</dd>
                <dt>Nickname</dt>
                <dd>{p.nickname || "—"}</dd>
                <dt>Location</dt>
                <dd>{p.location || "—"}</dd>
                <dt>About</dt>
                <dd style={{ whiteSpace: "pre-wrap" }}>{p.about || "—"}</dd>
                <dt>How they talk</dt>
                <dd>{p.speaking_style || "—"}</dd>
                <dt>Joined</dt>
                <dd>{new Date(user.created_at * 1000).toLocaleString()}</dd>
                <dt>User ID</dt>
                <dd className="mono small">{user.user_id}</dd>
              </dl>
            ) : (
              <p className="muted small">Hasn&apos;t filled in their profile.</p>
            )}
          </section>
          <section className="card">
            <h2>Callers ({extras.callers.length})</h2>
            {extras.callers.length === 0 ? (
              <p className="muted small">None yet.</p>
            ) : (
              <ul className="timeline">
                {extras.callers.map((c) => (
                  <li key={c.id}>
                    <Avatar name={c.name} />
                    <div>
                      <strong>{c.name}</strong>
                      <div className="small muted">
                        {[c.relationship, c.location, languageName(c.language)].filter(Boolean).join(" · ")}
                      </div>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </section>
          <section className="card">
            <h2>Voices ({extras.voices.length})</h2>
            {extras.voices.length === 0 ? (
              <p className="muted small">Using built-in voices only.</p>
            ) : (
              <ul className="timeline">
                {extras.voices.map((v) => (
                  <li key={v.id} className="row spread">
                    <span>
                      <strong>{v.name}</strong> {v.source === "clone" && <span className="pill green">Clone</span>}
                    </span>
                    <AdminButton
                      url={`/api/admin/voices/${v.id}`}
                      method="DELETE"
                      label="Remove"
                      className="danger"
                      confirmText={`Remove ${v.name}?${v.source === "clone" ? " The clone is deleted from ElevenLabs." : ""}`}
                    />
                  </li>
                ))}
              </ul>
            )}
          </section>
        </div>
      </div>

      <div className="section-head">
        <h2>Top-ups</h2>
      </div>
      <section className="card" style={{ marginTop: 10 }}>
        <DepositTable deposits={deposits} />
      </section>

      <div className="section-head">
        <h2>Calls</h2>
      </div>
      <section className="card" style={{ marginTop: 10 }}>
        <CallList calls={calls} showUser={false} />
      </section>

      <div className="section-head">
        <h2>Balance history</h2>
      </div>
      <section className="card" style={{ marginTop: 10 }}>
        {extras.usage.length === 0 ? (
          <div className="empty">Nothing yet.</div>
        ) : (
          <div className="table-wrap">
            <table className="admin-table">
              <thead>
                <tr>
                  <th>When</th>
                  <th>Type</th>
                  <th>Detail</th>
                  <th>Amount</th>
                </tr>
              </thead>
              <tbody>
                {extras.usage.map((e) => (
                  <tr key={e.id}>
                    <td>{new Date(e.created_at * 1000).toLocaleString()}</td>
                    <td>{e.kind}</td>
                    <td className="wrap small muted">{e.kind === "call" ? `${Math.floor(e.quantity / 60)}m ${e.quantity % 60}s` : e.detail}</td>
                    <td className={e.amount_micros < 0 ? "amount-neg" : "amount-pos"}>{formatUsd(e.amount_micros)}</td>
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
