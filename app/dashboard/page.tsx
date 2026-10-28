import Link from "next/link";
import { LogoutButton } from "@/components/LogoutButton";
import { config, formatUsd } from "@/lib/config.ts";
import { pageUser } from "@/lib/page-auth.ts";
import { getCredit, listScenarios, listUsage } from "@/lib/repo.ts";
import { VOICES } from "@/lib/voices.ts";

export const dynamic = "force-dynamic";

const KIND_LABEL: Record<string, string> = { voice: "Practice call", llm: "AI setup", credit: "Credit" };

export default async function Dashboard() {
  const user = await pageUser();
  const scenarios = listScenarios(user.accountId);
  const credit = getCredit(user.accountId);
  const usage = listUsage(user.accountId, 15);
  const minutesLeft = Math.max(0, Math.floor(credit / config.pricePerMinuteMicros));

  return (
    <main>
      <div className="row spread">
        <div>
          <h1>{user.accountName}</h1>
          <p className="muted small">{user.email}</p>
        </div>
        <LogoutButton />
      </div>

      <section className="card row spread">
        <div>
          <div className="muted small">Credit</div>
          <strong style={{ fontSize: "1.4rem" }}>{formatUsd(credit)}</strong>{" "}
          <span className="muted small">
            about {minutesLeft} min of practice at {formatUsd(config.pricePerMinuteMicros)}/min
          </span>
        </div>
        <Link className="button" href="/new">
          New scenario
        </Link>
      </section>

      <section className="card">
        <h2>Your scenarios</h2>
        {scenarios.length === 0 ? (
          <p className="muted">No scenarios yet. Create one to start practicing.</p>
        ) : (
          <ul className="list">
            {scenarios.map((s) => (
              <li key={s.id} className="row spread">
                <div>
                  <Link href={`/scenarios/${s.id}`}>
                    <strong>{s.name}</strong>
                  </Link>
                  <div className="muted small">
                    Voice: {VOICES.find((v) => v.id === s.voice_id)?.name ?? "Unknown"}
                    {!s.agent_id && " · voice not set up"}
                  </div>
                </div>
                <Link className="button secondary" href={`/scenarios/${s.id}`}>
                  Practice
                </Link>
              </li>
            ))}
          </ul>
        )}
      </section>

      <section className="card">
        <h2>Recent usage</h2>
        {usage.length === 0 ? (
          <p className="muted">Nothing yet.</p>
        ) : (
          <table>
            <thead>
              <tr>
                <th>When</th>
                <th>What</th>
                <th>Amount</th>
              </tr>
            </thead>
            <tbody>
              {usage.map((e) => (
                <tr key={e.id}>
                  <td>{new Date(e.created_at * 1000).toLocaleString()}</td>
                  <td>
                    {KIND_LABEL[e.kind] ?? e.kind}
                    {e.kind === "voice" && ` · ${Math.ceil(e.quantity / 60)} min`}
                    {e.kind !== "voice" && e.detail && ` · ${e.detail}`}
                  </td>
                  <td className={e.amount_micros < 0 ? "" : "ok"}>{formatUsd(e.amount_micros)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </section>
    </main>
  );
}
