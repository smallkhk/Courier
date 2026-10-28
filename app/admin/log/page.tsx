import { listAdminLog } from "@/lib/admin.ts";

export default function AdminLog() {
  const entries = listAdminLog();
  return (
    <>
      <h1>Activity log</h1>
      <p className="muted">Every admin action: who did it, to whom, and when. Entries can&apos;t be edited or deleted.</p>
      <section className="card">
        {entries.length === 0 ? (
          <div className="empty">No admin actions yet.</div>
        ) : (
          <div className="table-wrap">
            <table className="admin-table">
              <thead>
                <tr>
                  <th>When</th>
                  <th>Admin</th>
                  <th>Action</th>
                  <th>Target</th>
                  <th>Detail</th>
                </tr>
              </thead>
              <tbody>
                {entries.map((e) => (
                  <tr key={e.id}>
                    <td>{new Date(e.created_at * 1000).toLocaleString()}</td>
                    <td>{e.admin_email}</td>
                    <td>
                      <span className="pill">{e.action}</span>
                    </td>
                    <td>{e.target}</td>
                    <td className="wrap small muted">{e.detail}</td>
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
