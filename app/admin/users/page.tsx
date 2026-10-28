import Link from "next/link";
import { Avatar } from "@/components/Avatar";
import { listUsers } from "@/lib/admin.ts";
import { formatUsd } from "@/lib/config.ts";

const FILTERS = [
  { key: "", label: "All" },
  { key: "paying", label: "Paying" },
  { key: "blocked", label: "Blocked" },
  { key: "admins", label: "Admins" },
];
const PAGE = 50;

export default async function AdminUsers({ searchParams }: { searchParams: Promise<Record<string, string | undefined>> }) {
  const { q = "", filter = "", page = "0" } = await searchParams;
  const pageNum = Math.max(0, Number(page) || 0);
  const { users, total } = listUsers({ search: q.trim(), filter, limit: PAGE, offset: pageNum * PAGE });
  const link = (over: Record<string, string>) => `/admin/users?${new URLSearchParams({ q, filter, ...over })}`;

  return (
    <>
      <h1>Users</h1>
      <p className="muted">{total} {total === 1 ? "user" : "users"}</p>
      <form className="row" style={{ marginTop: 12 }}>
        <input name="q" defaultValue={q} placeholder="Search by email or name" style={{ flex: 1, minWidth: 220 }} />
        <input type="hidden" name="filter" value={filter} />
        <button type="submit">Search</button>
      </form>
      <div className="filter-chips" style={{ marginTop: 12 }}>
        {FILTERS.map((f) => (
          <Link key={f.key} href={link({ filter: f.key, page: "0" })} className={filter === f.key ? "active" : ""}>
            {f.label}
          </Link>
        ))}
      </div>
      <section className="card">
        {users.length === 0 ? (
          <div className="empty">No users found.</div>
        ) : (
          <div className="table-wrap">
            <table className="admin-table">
              <thead>
                <tr>
                  <th>User</th>
                  <th>Balance</th>
                  <th>Paid in</th>
                  <th>Calls</th>
                  <th>Joined</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                {users.map((u) => (
                  <tr key={u.user_id}>
                    <td>
                      <Link href={`/admin/users/${u.user_id}`} className="row" style={{ flexWrap: "nowrap", gap: 10, color: "var(--text)" }}>
                        <Avatar name={u.full_name || u.email} />
                        <span>
                          <strong>{u.full_name || "No name yet"}</strong>
                          <br />
                          <span className="small muted">{u.email}</span>
                        </span>
                      </Link>
                    </td>
                    <td>{formatUsd(u.credit_micros)}</td>
                    <td>{formatUsd(u.deposited_micros)}</td>
                    <td>{u.calls}</td>
                    <td>{new Date(u.created_at * 1000).toLocaleDateString()}</td>
                    <td>
                      <span className="row" style={{ gap: 4 }}>
                        {u.blocked_at ? <span className="pill red">Blocked</span> : <span className="pill green">Active</span>}
                        {u.is_admin ? <span className="pill amber">Admin</span> : null}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
      {total > PAGE && (
        <div className="row spread" style={{ marginTop: 12 }}>
          {pageNum > 0 ? <Link className="button secondary" href={link({ page: String(pageNum - 1) })}>← Newer</Link> : <span />}
          <span className="small muted">
            Page {pageNum + 1} of {Math.ceil(total / PAGE)}
          </span>
          {(pageNum + 1) * PAGE < total ? <Link className="button secondary" href={link({ page: String(pageNum + 1) })}>Older →</Link> : <span />}
        </div>
      )}
    </>
  );
}
