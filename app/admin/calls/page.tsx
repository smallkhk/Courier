import { CallList } from "@/components/admin/CallList";
import { listAllCalls } from "@/lib/admin.ts";

export default function AdminCalls() {
  const calls = listAllCalls({ limit: 200 });
  return (
    <>
      <h1>Calls</h1>
      <p className="muted">The latest 200 calls across all users, with topics, summaries and full transcripts.</p>
      <section className="card">
        <CallList calls={calls} />
      </section>
    </>
  );
}
