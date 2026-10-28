import Link from "next/link";
import { AdminButton } from "@/components/admin/AdminButton";
import { listAllVoices } from "@/lib/admin.ts";

export default function AdminVoices() {
  const voices = listAllVoices();
  return (
    <>
      <h1>Voices</h1>
      <p className="muted">
        Every saved and cloned voice. Removing a clone also deletes it from your ElevenLabs account, which frees a voice slot.
      </p>
      <section className="card">
        {voices.length === 0 ? (
          <div className="empty">No saved or cloned voices yet.</div>
        ) : (
          <div className="table-wrap">
            <table className="admin-table">
              <thead>
                <tr>
                  <th>Voice</th>
                  <th>Type</th>
                  <th>User</th>
                  <th>Added</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {voices.map((v) => (
                  <tr key={v.id}>
                    <td>
                      <strong>{v.name}</strong>
                      <div className="mono small faint">{v.voice_id}</div>
                    </td>
                    <td>{v.source === "clone" ? <span className="pill green">Clone</span> : <span className="pill">Library</span>}</td>
                    <td>
                      <Link href={`/admin/users/${v.user_id}`}>{v.email}</Link>
                    </td>
                    <td>{new Date(v.created_at * 1000).toLocaleDateString()}</td>
                    <td>
                      <AdminButton
                        url={`/api/admin/voices/${v.id}`}
                        method="DELETE"
                        label="Remove"
                        className="danger"
                        confirmText={`Remove ${v.name} from ${v.email}?${v.source === "clone" ? " The clone is deleted from ElevenLabs." : ""}`}
                      />
                    </td>
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
