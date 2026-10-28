"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import type { SettingDef } from "@/lib/settings.ts";
import { api } from "../api";

type Setting = SettingDef & { value: string };

const GROUPS: SettingDef["group"][] = ["General", "Pricing", "Calls", "Payments", "AI and voice"];

export function SettingsForm({ settings }: { settings: Setting[] }) {
  const router = useRouter();
  const [values, setValues] = useState(Object.fromEntries(settings.map((s) => [s.key, s.value])));
  const [status, setStatus] = useState<{ ok?: string; error?: string }>({});
  const [busy, setBusy] = useState(false);
  const dirty = settings.filter((s) => values[s.key] !== s.value);

  async function save(event: React.FormEvent) {
    event.preventDefault();
    setBusy(true);
    setStatus({});
    try {
      const changes = Object.fromEntries(dirty.map((s) => [s.key, values[s.key]]));
      await api("/api/admin/settings", { method: "PUT", body: JSON.stringify({ values: changes }) });
      setStatus({ ok: `Saved ${dirty.length} change${dirty.length === 1 ? "" : "s"}. Live now.` });
      router.refresh();
    } catch (err) {
      setStatus({ error: (err as Error).message });
    } finally {
      setBusy(false);
    }
  }

  return (
    <form onSubmit={save}>
      {GROUPS.map((group) => (
        <section key={group} className="card stack">
          <h2>{group}</h2>
          {settings
            .filter((s) => s.group === group)
            .map((s) => (
              <div key={s.key}>
                {s.kind === "boolean" ? (
                  <div className="row spread">
                    <div>
                      <label style={{ marginBottom: 0 }}>{s.label}</label>
                      <div className="small muted">{s.help}</div>
                    </div>
                    <div className="toggle" role="group" aria-label={s.label}>
                      <button type="button" className={values[s.key] === "true" ? "on" : ""} onClick={() => setValues({ ...values, [s.key]: "true" })}>
                        On
                      </button>
                      <button type="button" className={values[s.key] === "false" ? "on" : ""} onClick={() => setValues({ ...values, [s.key]: "false" })}>
                        Off
                      </button>
                    </div>
                  </div>
                ) : (
                  <>
                    <label htmlFor={s.key}>{s.label}</label>
                    <input
                      id={s.key}
                      className={s.kind === "trc20" || s.kind === "bep20" ? "mono" : undefined}
                      type={s.kind === "number" || s.kind === "integer" ? "number" : "text"}
                      step={s.kind === "number" ? "0.01" : s.kind === "integer" ? "1" : undefined}
                      min={s.min}
                      value={values[s.key]}
                      onChange={(e) => setValues({ ...values, [s.key]: e.target.value })}
                    />
                    <div className="small muted" style={{ marginTop: 4 }}>
                      {s.help}
                    </div>
                  </>
                )}
              </div>
            ))}
        </section>
      ))}
      <div className="card row spread" style={{ position: "sticky", bottom: 12, zIndex: 5 }}>
        <span className="small muted">{dirty.length ? `${dirty.length} unsaved change${dirty.length === 1 ? "" : "s"}` : "All saved"}</span>
        {status.error && <span className="error small">{status.error}</span>}
        {status.ok && <span className="ok small">{status.ok}</span>}
        <button type="submit" disabled={busy || dirty.length === 0}>
          {busy ? "Saving…" : "Save changes"}
        </button>
      </div>
    </form>
  );
}
