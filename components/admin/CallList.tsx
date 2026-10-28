import Link from "next/link";
import type { AdminCall } from "@/lib/admin.ts";
import { formatUsd } from "@/lib/config.ts";

function clock(secs: number): string {
  return `${Math.floor(secs / 60)}:${String(secs % 60).padStart(2, "0")}`;
}

export function CallList({ calls, showUser = true }: { calls: AdminCall[]; showUser?: boolean }) {
  if (calls.length === 0) return <div className="empty">No calls yet.</div>;
  return (
    <ul className="history">
      {calls.map((c) => (
        <li key={c.id}>
          <details>
            <summary className="row spread">
              <span>
                <span className="chev">›</span> <strong>{new Date(c.created_at * 1000).toLocaleString()}</strong>
                <span className="small muted">
                  {" "}
                  · {showUser && <>{c.email} → </>}
                  {c.caller_name ?? "deleted caller"}
                </span>
              </span>
              <span className="row" style={{ gap: 6 }}>
                {c.status === "failed" && <span className="pill red">Failed</span>}
                <span className="pill">{clock(c.duration_secs)}</span>
                <span className="pill">{formatUsd(c.charged_micros)}</span>
              </span>
            </summary>
            <div className="stack" style={{ paddingBottom: 16 }}>
              {showUser && (
                <p className="small">
                  <Link href={`/admin/users/${c.user_id}`}>Open user →</Link>
                  <span className="faint"> · ElevenLabs conversation {c.conversation_id}</span>
                </p>
              )}
              {c.topics && (
                <p className="small">
                  <span className="faint">Topics · </span>
                  {c.topics}
                </p>
              )}
              {c.summary && <div className="memory">{c.summary}</div>}
              <div className="chat">
                {c.transcript.map((line, i) => (
                  <div key={i} className={`bubble ${line.role === "agent" ? "me" : "them"}`}>
                    {line.message}
                  </div>
                ))}
              </div>
            </div>
          </details>
        </li>
      ))}
    </ul>
  );
}
