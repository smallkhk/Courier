"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import type { Profile } from "@/lib/repo.ts";
import { api } from "./api";

export function ProfileForm({ initial }: { initial: Profile }) {
  const router = useRouter();
  const [values, setValues] = useState(initial);
  const [status, setStatus] = useState<{ ok?: string; error?: string }>({});
  const [busy, setBusy] = useState(false);
  const set = (key: keyof Profile) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) =>
    setValues({ ...values, [key]: e.target.value });

  async function submit(event: React.FormEvent) {
    event.preventDefault();
    setBusy(true);
    setStatus({});
    try {
      await api("/api/profile", { method: "PUT", body: JSON.stringify(values) });
      setStatus({ ok: "Saved." });
      router.refresh();
    } catch (err) {
      setStatus({ error: (err as Error).message });
    } finally {
      setBusy(false);
    }
  }

  return (
    <form className="card stack" onSubmit={submit}>
      <div className="grid2">
        <div>
          <label htmlFor="full_name">Your name</label>
          <input id="full_name" value={values.full_name} onChange={set("full_name")} required maxLength={100} />
        </div>
        <div>
          <label htmlFor="nickname">What people call you (optional)</label>
          <input id="nickname" value={values.nickname} onChange={set("nickname")} maxLength={60} placeholder="e.g. Ada, Bros, Sis" />
        </div>
      </div>
      <div>
        <label htmlFor="location">Where you live</label>
        <input id="location" value={values.location} onChange={set("location")} maxLength={120} placeholder="e.g. Lekki, Lagos" />
      </div>
      <div>
        <label htmlFor="about">About you</label>
        <textarea
          id="about"
          rows={6}
          value={values.about}
          onChange={set("about")}
          maxLength={4000}
          placeholder="Work, family, what's going on in your life, things people usually ask you about. The AI only uses what you write here."
        />
      </div>
      <div>
        <label htmlFor="speaking_style">How you talk</label>
        <textarea
          id="speaking_style"
          rows={3}
          value={values.speaking_style}
          onChange={set("speaking_style")}
          maxLength={2000}
          placeholder="e.g. Relaxed, mixes English and Pidgin, laughs a lot, says 'abeg' and 'no wahala'."
        />
      </div>
      {status.error && <p className="error">{status.error}</p>}
      {status.ok && <p className="ok">{status.ok}</p>}
      <div>
        <button type="submit" disabled={busy}>
          Save profile
        </button>
      </div>
    </form>
  );
}
