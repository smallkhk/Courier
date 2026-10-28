"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import type { PracticeSession } from "@/lib/repo.ts";
import type { Voice } from "@/lib/voices.ts";
import { api, usd } from "./api";
import { PracticePanel } from "./PracticePanel";
import { ScenarioForm, type ScenarioValues } from "./ScenarioForm";

type Scenario = ScenarioValues & { id: string; voiceReady: boolean };

export function ScenarioView({
  initialScenario,
  initialSessions,
  initialCredit,
  voices,
}: {
  initialScenario: Scenario;
  initialSessions: PracticeSession[];
  initialCredit: number;
  voices: Voice[];
}) {
  const router = useRouter();
  const [scenario, setScenario] = useState(initialScenario);
  const [sessions, setSessions] = useState(initialSessions);
  const [credit, setCredit] = useState(initialCredit);
  const [saved, setSaved] = useState(false);
  const [deleteError, setDeleteError] = useState("");

  // Pick up calls that finished while this page wasn't open.
  useEffect(() => {
    api<{ creditMicros: number; sessions: PracticeSession[] }>(`/api/scenarios/${scenario.id}/settle`, {
      method: "POST",
    })
      .then((result) => {
        setCredit(result.creditMicros);
        setSessions(result.sessions);
      })
      .catch(() => {});
  }, [scenario.id]);

  return (
    <>
      <p className="muted small">Credit: {usd(credit)}</p>
      <PracticePanel
        scenarioId={scenario.id}
        voiceReady={scenario.voiceReady}
        onSettled={(result) => {
          setCredit(result.creditMicros);
          setSessions(result.sessions);
        }}
      />

      <section className="card">
        <h2>Past calls</h2>
        {sessions.length === 0 ? (
          <p className="muted">No calls yet.</p>
        ) : (
          <ul className="list">
            {sessions.map((s) => (
              <li key={s.id}>
                <details>
                  <summary>
                    {new Date(s.started_at * 1000).toLocaleString()} · {Math.floor(s.duration_secs / 60)}m{" "}
                    {s.duration_secs % 60}s · {usd(s.charged_micros)}
                    {s.status === "failed" && " · failed"}
                  </summary>
                  <div className="chat" style={{ marginTop: 8 }}>
                    {s.transcript.length === 0 && <p className="muted small">No transcript.</p>}
                    {s.transcript.map((line, i) => (
                      <div key={i} className={`bubble ${line.role}`}>
                        {line.message}
                      </div>
                    ))}
                  </div>
                </details>
              </li>
            ))}
          </ul>
        )}
      </section>

      <section className="card">
        <details>
          <summary>
            <strong>Edit scenario</strong>
          </summary>
          <div style={{ marginTop: 12 }}>
            <ScenarioForm
              voices={voices}
              submitLabel="Save changes"
              initial={scenario}
              onSubmit={async (values) => {
                const result = await api<{ scenario: Scenario }>(`/api/scenarios/${scenario.id}`, {
                  method: "PUT",
                  body: JSON.stringify(values),
                });
                setScenario(result.scenario);
                setSaved(true);
                router.refresh();
              }}
            />
            {saved && <p className="ok small">Saved. Your next call uses the new version.</p>}
          </div>
        </details>
      </section>

      <section className="card row spread">
        <span className="muted small">Deleting removes the scenario and its call history.</span>
        <button
          className="danger"
          onClick={async () => {
            if (!confirm(`Delete "${scenario.name}"? This can't be undone.`)) return;
            try {
              await api(`/api/scenarios/${scenario.id}`, { method: "DELETE" });
              router.push("/dashboard");
              router.refresh();
            } catch (err) {
              setDeleteError((err as Error).message);
            }
          }}
        >
          Delete scenario
        </button>
        {deleteError && <p className="error">{deleteError}</p>}
      </section>
    </>
  );
}
