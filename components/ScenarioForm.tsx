"use client";

import { useState } from "react";
import type { Voice } from "@/lib/voices.ts";

export type ScenarioValues = { name: string; prompt: string; firstMessage: string; voiceId: string };

export function ScenarioForm({
  initial,
  voices,
  submitLabel,
  onSubmit,
}: {
  initial: ScenarioValues;
  voices: Voice[];
  submitLabel: string;
  onSubmit: (values: ScenarioValues) => Promise<void>;
}) {
  const [values, setValues] = useState(initial);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  const set = (key: keyof ScenarioValues) => (event: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) =>
    setValues({ ...values, [key]: event.target.value });

  async function submit(event: React.FormEvent) {
    event.preventDefault();
    setBusy(true);
    setError("");
    try {
      await onSubmit(values);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <form className="stack" onSubmit={submit}>
      <div>
        <label htmlFor="name">Scenario name</label>
        <input id="name" value={values.name} onChange={set("name")} maxLength={100} required />
      </div>
      <div>
        <label htmlFor="voice">Voice</label>
        <select id="voice" value={values.voiceId} onChange={set("voiceId")}>
          {voices.map((voice) => (
            <option key={voice.id} value={voice.id}>
              {voice.name} ({voice.description})
            </option>
          ))}
        </select>
      </div>
      <div>
        <label htmlFor="firstMessage">Opening line (optional)</label>
        <input
          id="firstMessage"
          value={values.firstMessage}
          onChange={set("firstMessage")}
          maxLength={500}
          placeholder="What the AI says when the call connects. Leave empty to speak first."
        />
      </div>
      <div>
        <label htmlFor="prompt">Scenario prompt</label>
        <textarea id="prompt" className="code" value={values.prompt} onChange={set("prompt")} required />
      </div>
      {error && <p className="error">{error}</p>}
      <div className="row">
        <button type="submit" disabled={busy}>
          {busy ? "Checking and saving…" : submitLabel}
        </button>
        <span className="muted small">Every save is checked against our content rules.</span>
      </div>
    </form>
  );
}
