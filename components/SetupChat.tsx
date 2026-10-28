"use client";

import { useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { extractScenarioPrompt, suggestName } from "@/lib/extract.ts";
import type { Voice } from "@/lib/voices.ts";
import { api } from "./api";
import { ScenarioForm } from "./ScenarioForm";

type Turn = { role: "user" | "assistant"; content: string };

const GREETING =
  "Hi! I'll help you set up a voice practice scenario. Who should the AI play, and what do you want to practice?";

export function SetupChat({ voices }: { voices: Voice[] }) {
  const router = useRouter();
  const [turns, setTurns] = useState<Turn[]>([]);
  const [draft, setDraft] = useState("");
  const [streaming, setStreaming] = useState(false);
  const [error, setError] = useState("");
  const bottom = useRef<HTMLDivElement>(null);

  useEffect(() => bottom.current?.scrollIntoView({ block: "end" }), [turns]);

  const lastAssistant = [...turns].reverse().find((t) => t.role === "assistant");
  const scenarioPrompt = !streaming && lastAssistant ? extractScenarioPrompt(lastAssistant.content) : null;

  async function send(event: React.FormEvent) {
    event.preventDefault();
    const text = draft.trim();
    if (!text || streaming) return;
    const history: Turn[] = [...turns, { role: "user", content: text }];
    setTurns([...history, { role: "assistant", content: "" }]);
    setDraft("");
    setStreaming(true);
    setError("");
    try {
      const response = await fetch("/api/setup", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ messages: history }),
      });
      if (!response.ok || !response.body) {
        const data = await response.json().catch(() => ({}));
        throw new Error((data as { error?: string }).error ?? "Request failed");
      }
      const reader = response.body.pipeThrough(new TextDecoderStream()).getReader();
      let reply = "";
      for (;;) {
        const { value, done } = await reader.read();
        if (done) break;
        reply += value;
        setTurns([...history, { role: "assistant", content: reply }]);
      }
    } catch (err) {
      setError((err as Error).message);
      setTurns(history.slice(0, -1));
      setDraft(text);
    } finally {
      setStreaming(false);
    }
  }

  return (
    <>
      <section className="card">
        <div className="chat" aria-live="polite">
          <div className="bubble assistant">{GREETING}</div>
          {turns.map((turn, i) => (
            <div key={i} className={`bubble ${turn.role}`}>
              {turn.content || "…"}
            </div>
          ))}
          <div ref={bottom} />
        </div>
        <form className="row" style={{ marginTop: 12 }} onSubmit={send}>
          <textarea
            rows={2}
            style={{ flex: 1, minWidth: 0 }}
            value={draft}
            onChange={(e) => setDraft(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === "Enter" && !e.shiftKey) {
                e.preventDefault();
                e.currentTarget.form?.requestSubmit();
              }
            }}
            placeholder="e.g. A busy IT director at a hospital who I'm cold-calling about our scheduling software. Medium difficulty."
            aria-label="Message"
          />
          <button type="submit" disabled={streaming || !draft.trim()}>
            Send
          </button>
        </form>
        {error && <p className="error">{error}</p>}
      </section>

      {scenarioPrompt && (
        <section className="card">
          <h2>Save this scenario</h2>
          <p className="muted small">Review or edit the prompt, pick a voice, then save to start practicing.</p>
          <ScenarioForm
            key={scenarioPrompt}
            voices={voices}
            submitLabel="Save and practice"
            initial={{ name: suggestName(scenarioPrompt), prompt: scenarioPrompt, firstMessage: "", voiceId: voices[0].id }}
            onSubmit={async (values) => {
              const { scenario } = await api<{ scenario: { id: string } }>("/api/scenarios", {
                method: "POST",
                body: JSON.stringify(values),
              });
              router.push(`/scenarios/${scenario.id}`);
            }}
          />
        </section>
      )}
    </>
  );
}
