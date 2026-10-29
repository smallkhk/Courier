"use client";

import { useRouter } from "next/navigation";
import { useMemo, useState } from "react";
import type { CallerFields } from "@/lib/repo.ts";
import type { Voice } from "@/lib/voices.ts";
import { LANGUAGES } from "@/lib/languages.ts";
import { api } from "./api";

export function CallerForm({
  callerId,
  initial,
  voices,
}: {
  callerId?: string;
  initial: CallerFields;
  voices: Voice[];
}) {
  const router = useRouter();
  const [values, setValues] = useState(initial);
  const [status, setStatus] = useState<{ ok?: string; error?: string }>({});
  const [busy, setBusy] = useState(false);
  const timezones = useMemo(() => {
    const zones = Intl.supportedValuesOf("timeZone");
    return zones.includes(values.timezone) ? zones : [values.timezone, ...zones];
  }, [values.timezone]);
  const set =
    (key: keyof CallerFields) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) =>
      setValues({ ...values, [key]: e.target.value });

  async function submit(event: React.FormEvent) {
    event.preventDefault();
    setBusy(true);
    setStatus({});
    try {
      if (callerId) {
        await api(`/api/callers/${callerId}`, { method: "PUT", body: JSON.stringify(values) });
        setStatus({ ok: "Saved." });
        router.refresh();
      } else {
        const { caller } = await api<{ caller: { id: string } }>("/api/callers", {
          method: "POST",
          body: JSON.stringify(values),
        });
        router.push(`/callers/${caller.id}`);
      }
    } catch (err) {
      setStatus({ error: (err as Error).message });
    } finally {
      setBusy(false);
    }
  }

  async function remove() {
    if (!callerId || !confirm(`Delete ${values.name}? Their call history stays in your usage.`)) return;
    await api(`/api/callers/${callerId}`, { method: "DELETE" });
    router.push("/home");
    router.refresh();
  }

  return (
    <form className="stack" onSubmit={submit}>
      <div className="grid2">
        <div>
          <label htmlFor="name">Their name</label>
          <input id="name" value={values.name} onChange={set("name")} required maxLength={80} />
        </div>
        <div>
          <label htmlFor="relationship">Who they are to you</label>
          <input id="relationship" value={values.relationship} onChange={set("relationship")} maxLength={80} placeholder="e.g. brother, mum, client" />
        </div>
      </div>
      <div>
        <label>Type</label>
        <div className="net-toggle">
          <button type="button" className={values.kind === "personal" ? "active" : ""} onClick={() => setValues({ ...values, kind: "personal" })}>
            👪 Personal
            <small>family, friends</small>
          </button>
          <button type="button" className={values.kind === "client" ? "active" : ""} onClick={() => setValues({ ...values, kind: "client" })}>
            💼 Client
            <small>business, projects</small>
          </button>
        </div>
      </div>
      {values.kind === "client" && (
        <>
          <div className="grid2">
            <div>
              <label htmlFor="company">Company</label>
              <input id="company" value={values.company} onChange={set("company")} maxLength={120} placeholder="e.g. Tunde Foods Ltd" />
            </div>
            <div>
              <label htmlFor="budget">Budget discussed</label>
              <input id="budget" value={values.budget} onChange={set("budget")} maxLength={120} placeholder="e.g. ₦400k" />
            </div>
          </div>
          <div>
            <label htmlFor="project">Project</label>
            <textarea
              id="project"
              rows={3}
              value={values.project}
              onChange={set("project")}
              maxLength={2000}
              placeholder="e.g. Restaurant website with menu, online ordering and Paystack payments."
            />
          </div>
          <div>
            <label htmlFor="stage">Where you are with them</label>
            <select id="stage" value={values.stage} onChange={set("stage")}>
              <option value="">Not set</option>
              <option value="New lead">New lead</option>
              <option value="Discussing requirements">Discussing requirements</option>
              <option value="Quote sent">Quote sent</option>
              <option value="Deposit paid, building">Deposit paid, building</option>
              <option value="Reviewing with client">Reviewing with client</option>
              <option value="Delivered">Delivered</option>
            </select>
          </div>
        </>
      )}
      <div>
        <label htmlFor="about">About them</label>
        <textarea
          id="about"
          rows={4}
          value={values.about}
          onChange={set("about")}
          maxLength={4000}
          placeholder="What they do, family, what's going on with them, things to remember."
        />
      </div>
      <div>
        <label htmlFor="how_we_talk">How you two talk</label>
        <textarea
          id="how_we_talk"
          rows={3}
          value={values.how_we_talk}
          onChange={set("how_we_talk")}
          maxLength={2000}
          placeholder="e.g. Very casual, we joke a lot, he calls me 'baby sis', we speak Pidgin."
        />
      </div>
      <div className="grid2">
        <div>
          <label htmlFor="location">Where they are</label>
          <input id="location" value={values.location} onChange={set("location")} maxLength={120} placeholder="e.g. Abuja" />
        </div>
        <div>
          <label htmlFor="timezone">Their time zone</label>
          <select id="timezone" value={values.timezone} onChange={set("timezone")}>
            {timezones.map((tz) => (
              <option key={tz}>{tz}</option>
            ))}
          </select>
        </div>
      </div>
      <div className="grid2">
        <div>
          <label htmlFor="language">Call language</label>
          <select id="language" value={values.language} onChange={set("language")}>
            {LANGUAGES.map((l) => (
              <option key={l.code} value={l.code}>
                {l.name}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label htmlFor="voice_id">Voice</label>
          <select id="voice_id" value={values.voice_id} onChange={set("voice_id")}>
            {voices.map((v) => (
              <option key={v.id} value={v.id}>
                {v.name} ({v.description})
              </option>
            ))}
          </select>
        </div>
      </div>
      <div>
        <label htmlFor="opening">How the call opens</label>
        <select id="opening" value={values.opening} onChange={set("opening")}>
          <option value="wait">Wait for them to say hello, then greet back</option>
          <option value="greet">AI greets first (good morning / afternoon / evening)</option>
          <option value="custom">AI opens with my own line</option>
        </select>
      </div>
      {values.opening === "custom" && (
        <div>
          <label htmlFor="custom_opening">Opening line</label>
          <input id="custom_opening" value={values.custom_opening} onChange={set("custom_opening")} maxLength={300} placeholder="e.g. Bros! How far, you dey?" />
        </div>
      )}
      {status.error && <p className="error">{status.error}</p>}
      {status.ok && <p className="ok">{status.ok}</p>}
      <div className="row spread">
        <button type="submit" disabled={busy}>
          {callerId ? "Save changes" : "Save caller"}
        </button>
        {callerId && (
          <button type="button" className="danger" onClick={remove}>
            Delete caller
          </button>
        )}
      </div>
    </form>
  );
}
