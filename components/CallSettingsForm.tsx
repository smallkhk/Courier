"use client";

import { useState } from "react";
import type { CallSettings } from "@/lib/repo.ts";
import { api } from "./api";

function Slider({
  label,
  hint,
  left,
  right,
  min,
  max,
  step,
  value,
  onChange,
}: {
  label: string;
  hint: string;
  left: string;
  right: string;
  min: number;
  max: number;
  step: number;
  value: number;
  onChange: (v: number) => void;
}) {
  return (
    <div>
      <div className="row spread">
        <label style={{ marginBottom: 0 }}>{label}</label>
        <span className="small muted">{value.toFixed(2)}</span>
      </div>
      <input type="range" min={min} max={max} step={step} value={value} onChange={(e) => onChange(Number(e.target.value))} />
      <div className="row spread small faint">
        <span>{left}</span>
        <span>{right}</span>
      </div>
      <div className="small muted">{hint}</div>
    </div>
  );
}

export function CallSettingsForm({ initial }: { initial: CallSettings }) {
  const [v, setV] = useState(initial);
  const [status, setStatus] = useState<{ ok?: string; error?: string }>({});
  const [busy, setBusy] = useState(false);
  const set = <K extends keyof CallSettings>(key: K, value: CallSettings[K]) => setV({ ...v, [key]: value });

  async function save() {
    setBusy(true);
    setStatus({});
    try {
      await api("/api/profile/call-settings", {
        method: "PUT",
        body: JSON.stringify({ ...v, auto_hangup: Boolean(v.auto_hangup) }),
      });
      setStatus({ ok: "Saved. Your next call uses these settings." });
    } catch (err) {
      setStatus({ error: (err as Error).message });
    } finally {
      setBusy(false);
    }
  }

  return (
    <section className="card stack">
      <div>
        <h2 style={{ marginBottom: 2 }}>Voice and conversation</h2>
        <p className="small muted">How your AI sounds and behaves on calls.</p>
      </div>
      <Slider label="Speaking speed" hint="How fast it talks." left="Slower" right="Faster" min={0.7} max={1.2} step={0.05} value={v.voice_speed} onChange={(x) => set("voice_speed", x)} />
      <Slider label="Expressiveness" hint="Lower sounds more lively and emotional; higher sounds calmer and more even." left="Lively" right="Steady" min={0} max={1} step={0.05} value={v.voice_stability} onChange={(x) => set("voice_stability", x)} />
      <Slider label="Closeness to the voice" hint="Higher sticks closer to the original voice (best for your clone). Too high can add noise." left="Looser" right="Closer" min={0} max={1} step={0.05} value={v.voice_similarity} onChange={(x) => set("voice_similarity", x)} />
      <div>
        <label>How quickly it answers</label>
        <div className="net-toggle" style={{ gridTemplateColumns: "repeat(3, 1fr)" }}>
          {(
            [
              ["patient", "Patient", "lets them finish"],
              ["normal", "Normal", "balanced"],
              ["eager", "Quick", "jumps in fast"],
            ] as const
          ).map(([key, name, note]) => (
            <button key={key} type="button" className={v.turn_eagerness === key ? "active" : ""} onClick={() => set("turn_eagerness", key)}>
              {name}
              <small>{note}</small>
            </button>
          ))}
        </div>
      </div>
      <Slider label="Silence before it speaks up" hint="If the other person goes quiet this long (seconds), it checks in with them." left="3s" right="20s" min={3} max={20} step={1} value={v.turn_timeout} onChange={(x) => set("turn_timeout", Math.round(x))} />
      <div className="row spread">
        <div>
          <label style={{ marginBottom: 0 }}>Hang up by itself</label>
          <div className="small muted">After you both say goodbye, it ends the call. No need to press End.</div>
        </div>
        <div className="toggle">
          <button type="button" className={v.auto_hangup ? "on" : ""} onClick={() => set("auto_hangup", 1)}>
            On
          </button>
          <button type="button" className={!v.auto_hangup ? "on" : ""} onClick={() => set("auto_hangup", 0)}>
            Off
          </button>
        </div>
      </div>
      {status.error && <p className="error small">{status.error}</p>}
      {status.ok && <p className="ok small">{status.ok}</p>}
      <div>
        <button onClick={save} disabled={busy}>
          Save voice settings
        </button>
      </div>
    </section>
  );
}
