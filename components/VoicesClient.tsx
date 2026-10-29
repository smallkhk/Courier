"use client";

import { useRouter } from "next/navigation";
import { useRef, useState } from "react";
import type { SavedVoice } from "@/lib/repo.ts";
import type { Voice } from "@/lib/voices.ts";
import { api } from "./api";
import { Avatar } from "./Avatar";
import { PlayButton } from "./PlayButton";

type LibraryVoice = {
  public_owner_id: string;
  voice_id: string;
  name: string;
  accent: string;
  gender: string;
  age: string;
  descriptive: string;
  use_case: string;
  language: string;
  preview_url: string;
  description: string;
};

export function VoicesClient({
  saved,
  defaults,
  canClone,
}: {
  saved: SavedVoice[];
  defaults: Voice[];
  canClone: boolean;
}) {
  const router = useRouter();
  return (
    <>
      <section className="card">
        <h2>My voices</h2>
        {saved.length === 0 && (
          <div className="empty">
            <span className="emoji">🎧</span>
            No saved voices yet. Find one in the library below, or clone your own.
          </div>
        )}
        <div className="voice-grid">
          {saved.map((v) => (
            <div key={v.id} className="voice-card">
              <div className="row">
                <Avatar name={v.name} />
                <div style={{ minWidth: 0 }}>
                  <strong>{v.name}</strong>
                  <div className="small muted">{v.source === "clone" ? "Your cloned voice" : v.description}</div>
                </div>
              </div>
              {v.source === "clone" && <span className="pill green" style={{ alignSelf: "flex-start" }}>✨ Your voice</span>}
              {v.preview_url && <PlayButton src={v.preview_url} />}
              <button
                className="danger"
                onClick={async () => {
                  if (!confirm(`Remove ${v.name}?`)) return;
                  await api(`/api/voices/${v.id}`, { method: "DELETE" });
                  router.refresh();
                }}
              >
                Remove
              </button>
            </div>
          ))}
        </div>
        <details style={{ marginTop: 12 }}>
          <summary className="small muted">
            <span className="chev">›</span> Built-in voices ({defaults.length})
          </summary>
          <div className="voice-meta">
            {defaults.map((v) => (
              <span key={v.id} className="pill">
                {v.name} · {v.description}
              </span>
            ))}
          </div>
        </details>
      </section>
      <CloneVoice enabled={canClone} onDone={() => router.refresh()} />
      <Library onSaved={() => router.refresh()} />
    </>
  );
}

function Library({ onSaved }: { onSaved: () => void }) {
  const [filters, setFilters] = useState({ search: "", gender: "", accent: "", language: "" });
  const [voices, setVoices] = useState<LibraryVoice[]>([]);
  const [page, setPage] = useState(0);
  const [hasMore, setHasMore] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [savedIds, setSavedIds] = useState<Set<string>>(new Set());

  async function load(nextPage: number) {
    setBusy(true);
    setError("");
    try {
      const query = new URLSearchParams({ ...filters, page: String(nextPage) });
      const result = await api<{ voices: LibraryVoice[]; hasMore: boolean }>(`/api/voices/library?${query}`);
      setVoices(nextPage === 0 ? result.voices : [...voices, ...result.voices]);
      setHasMore(result.hasMore);
      setPage(nextPage);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  async function save(v: LibraryVoice) {
    setError("");
    try {
      await api("/api/voices/save", {
        method: "POST",
        body: JSON.stringify({
          publicOwnerId: v.public_owner_id,
          voiceId: v.voice_id,
          name: v.name,
          description: [v.gender, v.age, v.accent, v.descriptive].filter(Boolean).join(", "),
          previewUrl: v.preview_url ?? "",
        }),
      });
      setSavedIds(new Set(savedIds).add(v.voice_id));
      onSaved();
    } catch (err) {
      setError((err as Error).message);
    }
  }

  const set = (key: keyof typeof filters) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
    setFilters({ ...filters, [key]: e.target.value });

  return (
    <section className="card stack">
      <div>
        <h2 style={{ marginBottom: 2 }}>Voice library</h2>
        <p className="small muted">Thousands of realistic voices. Play a sample, then save the ones you like.</p>
      </div>
      <form
        className="lib-form"
        onSubmit={(e) => {
          e.preventDefault();
          void load(0);
        }}
      >
        <input placeholder="Search, e.g. Nigerian, warm, deep" value={filters.search} onChange={set("search")} aria-label="Search voices" />
        <select value={filters.gender} onChange={set("gender")} aria-label="Gender">
          <option value="">Any gender</option>
          <option value="male">Male</option>
          <option value="female">Female</option>
        </select>
        <input placeholder="Accent" value={filters.accent} onChange={set("accent")} aria-label="Accent" />
        <input placeholder="Language (en, fr…)" value={filters.language} onChange={set("language")} aria-label="Language" />
        <button type="submit" disabled={busy}>
          {busy ? "Searching…" : "Search"}
        </button>
      </form>
      {error && <p className="error">{error}</p>}
      {voices.length === 0 && !busy && (
        <div className="empty">
          <span className="emoji">🔎</span>
          Search to explore realistic voices. Try &quot;nigerian&quot;, &quot;warm&quot; or &quot;deep&quot;.
        </div>
      )}
      <div className="voice-grid">
        {voices.map((v) => (
          <div key={`${v.public_owner_id}-${v.voice_id}`} className="voice-card">
            <div className="row">
              <Avatar name={v.name} />
              <div style={{ minWidth: 0 }}>
                <strong>{v.name}</strong>
                <div className="small muted">{v.use_case || v.language}</div>
              </div>
            </div>
            <div className="voice-meta">
              {[v.gender, v.age, v.accent, v.descriptive].filter(Boolean).map((t) => (
                <span key={t} className="pill">
                  {t}
                </span>
              ))}
            </div>
            {v.preview_url && <PlayButton src={v.preview_url} />}
            <button className="secondary" disabled={savedIds.has(v.voice_id)} onClick={() => save(v)}>
              {savedIds.has(v.voice_id) ? "Saved" : "Save voice"}
            </button>
          </div>
        ))}
      </div>
      {hasMore && (
        <div>
          <button className="secondary" disabled={busy} onClick={() => load(page + 1)}>
            Load more
          </button>
        </div>
      )}
    </section>
  );
}

const MIN_SECONDS = 60;

// Read after the consent statement. About a minute to a minute and a half
// aloud, mixing questions, excitement and calm talk so the clone learns the
// whole range of the voice.
const PASSAGES = [
  "Good morning! I hope your day is going well. Let me tell you about my weekend. On Saturday I woke up early, made some tea, and sat outside for a while just listening to the birds. Later, my cousin called and asked if I wanted to go to the market with her. Of course I said yes! We bought tomatoes, peppers, fresh fish and a big bag of rice. The traffic on the way back was terrible, but we laughed the whole time. Have you ever been stuck in traffic and found yourself enjoying it anyway? In the evening, we cooked together and the whole house smelled amazing. Honestly, simple days like that are my favourite. Sunday was quieter. I read a little, called my mum, and planned the week ahead. Now I'm ready for whatever comes next.",
  "Hello, how are you doing today? I wanted to share a quick story. Last month I finally decided to learn something new, so I signed up for a short online course. The first week was exciting, but by the second week I was tired and wanted to give up. Can you believe that? Then a friend reminded me why I started, and that changed everything. I set a small goal: thirty minutes every evening, no excuses. Slowly, it started to make sense. Last Friday I finished the final lesson, and I felt so proud of myself! I even told my brother, who just laughed and said, \"I knew you would do it.\" The lesson for me is simple. Big things are just small steps, repeated again and again. So, what is the next thing you want to learn?",
  "Hey! It's been a while, hasn't it? A lot has happened since we last spoke. Work has been busy, but in a good way. We started a new project, and I'm working with some really kind people. On Wednesday we had a long meeting that went on for almost three hours. By the end, everyone was hungry, so we ordered jollof rice and chicken for the whole team. That made everybody happy! Outside of work, I've been trying to rest more and sleep on time. It's not always easy, especially when there's a good film on. Speaking of films, have you watched anything interesting lately? I'd love a good recommendation. Anyway, I just wanted to say hello and check in. Let's plan to catch up properly this weekend. Take care, and talk soon!",
];

function CloneVoice({ enabled, onDone }: { enabled: boolean; onDone: () => void }) {
  const [script, setScript] = useState("");
  const [passage, setPassage] = useState("");
  const [recording, setRecording] = useState(false);
  const [seconds, setSeconds] = useState(0);
  const [audio, setAudio] = useState<Blob | null>(null);
  const [consent, setConsent] = useState(false);
  const [busy, setBusy] = useState(false);
  const [status, setStatus] = useState<{ ok?: string; error?: string }>({});
  const recorder = useRef<MediaRecorder | null>(null);
  const timer = useRef<ReturnType<typeof setInterval> | null>(null);

  async function begin() {
    setStatus({});
    setAudio(null);
    try {
      const { script } = await api<{ script: string }>("/api/voices/clone/script", { method: "POST" });
      setScript(script);
      setPassage(PASSAGES[Math.floor(Math.random() * PASSAGES.length)]);
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      const chunks: Blob[] = [];
      const rec = new MediaRecorder(stream);
      rec.ondataavailable = (e) => chunks.push(e.data);
      rec.onstop = () => {
        stream.getTracks().forEach((t) => t.stop());
        setAudio(new Blob(chunks, { type: rec.mimeType }));
      };
      rec.start(1000);
      recorder.current = rec;
      setSeconds(0);
      setRecording(true);
      timer.current = setInterval(() => setSeconds((s) => s + 1), 1000);
    } catch (err) {
      const e = err as Error;
      setStatus({ error: e.name === "NotAllowedError" ? "Allow microphone access to record." : e.message });
    }
  }

  function stop() {
    recorder.current?.stop();
    if (timer.current) clearInterval(timer.current);
    setRecording(false);
  }

  async function submit() {
    if (!audio) return;
    setBusy(true);
    setStatus({});
    try {
      const form = new FormData();
      form.set("audio", audio, audio.type.includes("mp4") ? "sample.mp4" : "sample.webm");
      form.set("consent", consent ? "yes" : "no");
      const response = await fetch("/api/voices/clone", { method: "POST", body: form });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error((data as { error?: string }).error ?? "Cloning failed");
      setStatus({ ok: "Your voice is ready. Pick it as the voice for any caller." });
      setScript("");
      setPassage("");
      setAudio(null);
      onDone();
    } catch (err) {
      setStatus({ error: (err as Error).message });
    } finally {
      setBusy(false);
    }
  }

  return (
    <section className="card card-glow stack">
      <div>
        <h2 style={{ marginBottom: 2 }}>✨ Clone your voice</h2>
        <p className="small muted">Your AI can sound exactly like you.</p>
      </div>
      {!enabled ? (
        <p className="muted small">You already have a cloned voice. Remove it above to record a new one.</p>
      ) : (
        <>
          <p className="small muted">
            Only your own voice. You&apos;ll read a short consent statement with code words, then a short story we
            give you (about a minute). Use a quiet room.
          </p>
          {script && (
            <>
              <div className="script">
                <strong>1. Read this first:</strong>
                <br />
                {script}
              </div>
              <div className="script" style={{ fontSize: "1rem" }}>
                <strong>2. Then read this in your normal voice, like you&apos;re talking to a friend:</strong>
                <br />
                {passage}
              </div>
              <p className="small muted">
                Tips: hold the phone about a hand&apos;s length from your mouth, no music or TV in the background, and
                speak naturally. Smile, ask the questions like real questions, and don&apos;t rush. When you finish reading, press Stop.
              </p>
            </>
          )}
          <div className="row">
            {!recording ? (
              <button onClick={begin} disabled={busy}>
                {audio ? "Record again" : "Start recording"}
              </button>
            ) : (
              <>
                <span className="rec-dot" aria-hidden />
                <span className="timer">
                  {Math.floor(seconds / 60)}:{String(seconds % 60).padStart(2, "0")}
                </span>
                <button className="danger" onClick={stop} disabled={seconds < MIN_SECONDS}>
                  {seconds < MIN_SECONDS ? `Keep talking (${MIN_SECONDS - seconds}s)` : "Stop"}
                </button>
              </>
            )}
          </div>
          {audio && !recording && (
            <>
              <audio controls src={URL.createObjectURL(audio)} style={{ width: "100%" }} />
              <label className="row" style={{ fontWeight: 400 }}>
                <input type="checkbox" style={{ width: "auto" }} checked={consent} onChange={(e) => setConsent(e.target.checked)} />
                This is my own voice and I agree to it being cloned for my calls.
              </label>
              <div>
                <button onClick={submit} disabled={busy || !consent}>
                  {busy ? "Checking and cloning…" : "Clone my voice"}
                </button>
              </div>
            </>
          )}
        </>
      )}
      {status.error && <p className="error">{status.error}</p>}
      {status.ok && <p className="ok">{status.ok}</p>}
    </section>
  );
}
