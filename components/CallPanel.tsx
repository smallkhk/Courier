"use client";

import { ConversationProvider, useConversation } from "@elevenlabs/react";
import { useEffect, useRef, useState } from "react";
import { LANGUAGES } from "@/lib/languages.ts";
import type { Call } from "@/lib/repo.ts";
import type { Voice } from "@/lib/voices.ts";
import { api, usd } from "./api";

type Line = { key: string; role: "user" | "agent"; message: string };
type SettleResult = { live: number; processing: number; settled: number; creditMicros: number; calls: Call[] };

type Props = {
  callerId: string;
  callerName: string;
  defaultVoiceId: string;
  defaultLanguage: string;
  voices: Voice[];
  initialCalls: Call[];
  initialCredit: number;
};

export function CallPanel(props: Props) {
  return (
    <ConversationProvider>
      <Panel {...props} />
    </ConversationProvider>
  );
}

const WRAP_UP =
  "Time to end the call. Finish the point you're on, then say a warm, natural goodbye in your usual style. Keep it short.";

function clock(secs: number): string {
  const s = Math.max(0, Math.floor(secs));
  return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, "0")}`;
}

function Panel({ callerId, callerName, defaultVoiceId, defaultLanguage, voices, initialCalls, initialCredit }: Props) {
  const [topics, setTopics] = useState("");
  const [voiceId, setVoiceId] = useState(defaultVoiceId);
  const [language, setLanguage] = useState(defaultLanguage);
  const [calls, setCalls] = useState(initialCalls);
  const [credit, setCredit] = useState(initialCredit);
  const [lines, setLines] = useState<Line[]>([]);
  const [error, setError] = useState("");
  const [note, setNote] = useState("");
  const [starting, setStarting] = useState(false);
  const [muted, setMuted] = useState(false);
  const [elapsed, setElapsed] = useState(0);
  const [maxSeconds, setMaxSeconds] = useState(0);
  const [wrapping, setWrapping] = useState(false);
  const connectedAt = useRef<number | null>(null);
  const wrapSent = useRef(false);
  const settling = useRef(false);

  async function settle() {
    if (settling.current) return;
    settling.current = true;
    setNote("Saving the call…");
    try {
      for (let attempt = 0; attempt < 25; attempt++) {
        const result = await api<SettleResult>("/api/calls/settle", {
          method: "POST",
          body: JSON.stringify({ callerId }),
        });
        setCalls(result.calls);
        setCredit(result.creditMicros);
        if (result.live === 0 && result.processing === 0 && (result.settled > 0 || attempt >= 3)) {
          setNote(`Call saved. Balance: ${usd(result.creditMicros)}.`);
          return;
        }
        await new Promise((resolve) => setTimeout(resolve, 3000));
      }
      setNote("The call is still being processed. It will show up below shortly.");
    } catch (err) {
      setNote((err as Error).message);
    } finally {
      settling.current = false;
    }
  }

  const conversation = useConversation({
    micMuted: muted,
    onConnect: () => {
      connectedAt.current = Date.now();
    },
    onMessage: ({ message, role, response_id, event_id }) => {
      const key = response_id ?? `${role}-${event_id}`;
      setLines((current) => {
        const index = current.findIndex((line) => line.key === key);
        if (index === -1) return [...current, { key, role, message }];
        const next = current.slice();
        next[index] = { key, role, message };
        return next;
      });
    },
    onError: (message) => setError(message),
    onDisconnect: () => {
      connectedAt.current = null;
      void settle();
    },
  });

  const live = conversation.status === "connected";

  function requestWrapUp() {
    if (wrapSent.current) return;
    wrapSent.current = true;
    setWrapping(true);
    conversation.sendContextualUpdate(WRAP_UP);
  }

  // Call timer, and an automatic wrap-up 30 seconds before credit runs out.
  useEffect(() => {
    if (!live) return;
    const timer = setInterval(() => {
      if (!connectedAt.current) return;
      const secs = (Date.now() - connectedAt.current) / 1000;
      setElapsed(secs);
      if (maxSeconds && maxSeconds - secs <= 30) requestWrapUp();
    }, 500);
    return () => clearInterval(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [live, maxSeconds]);

  // After a wrap-up, hang up once the AI has finished its goodbye.
  const wasSpeaking = useRef(false);
  useEffect(() => {
    if (wrapping && wasSpeaking.current && !conversation.isSpeaking) {
      const t = setTimeout(() => conversation.endSession(), 1500);
      return () => clearTimeout(t);
    }
    wasSpeaking.current = conversation.isSpeaking;
  }, [conversation.isSpeaking, wrapping, conversation]);

  async function start() {
    setStarting(true);
    setError("");
    setNote("");
    setLines([]);
    setElapsed(0);
    setWrapping(false);
    wrapSent.current = false;
    try {
      await navigator.mediaDevices.getUserMedia({ audio: true });
      const result = await api<{ signedUrl: string; maxSeconds: number }>("/api/calls/start", {
        method: "POST",
        body: JSON.stringify({ callerId, topics, voiceId, language }),
      });
      setMaxSeconds(result.maxSeconds);
      conversation.startSession({ signedUrl: result.signedUrl });
    } catch (err) {
      const e = err as Error;
      setError(e.name === "NotAllowedError" ? "Allow microphone access so the AI can hear the call." : e.message);
    } finally {
      setStarting(false);
    }
  }

  const connecting = starting || conversation.status === "connecting";
  const lastSummary = calls.find((c) => c.summary)?.summary;

  return (
    <>
      {!live && (
        <section className="card stack">
          <h2>Today&apos;s call</h2>
          {lastSummary && (
            <p className="small muted">
              <strong>Last time:</strong> {lastSummary}
            </p>
          )}
          <div>
            <label htmlFor="topics">What should the AI talk about?</label>
            <textarea
              id="topics"
              rows={4}
              value={topics}
              onChange={(e) => setTopics(e.target.value)}
              maxLength={4000}
              placeholder={`e.g. Ask ${callerName} how the new job is going. Remind him about Dad's birthday on Saturday and ask if he can bring the cake.`}
            />
          </div>
          <div className="grid2">
            <div>
              <label htmlFor="voice">Voice</label>
              <select id="voice" value={voiceId} onChange={(e) => setVoiceId(e.target.value)}>
                {voices.map((v) => (
                  <option key={v.id} value={v.id}>
                    {v.name} ({v.description})
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label htmlFor="language">Language</label>
              <select id="language" value={language} onChange={(e) => setLanguage(e.target.value)}>
                {LANGUAGES.map((l) => (
                  <option key={l.code} value={l.code}>
                    {l.name}
                  </option>
                ))}
              </select>
            </div>
          </div>
          <p className="small muted">
            Call {callerName} from your phone and put it on speaker next to this device. Use the device&apos;s
            speakers, not headphones. When they pick up, press Start call. Balance: {usd(credit)}.
          </p>
          {error && <p className="error">{error}</p>}
          {note && <p className="small">{note}</p>}
          <div>
            <button onClick={start} disabled={connecting}>
              {connecting ? "Connecting…" : "Start call"}
            </button>
          </div>
        </section>
      )}

      {live && (
        <section className="card callscreen stack">
          <div className="row spread">
            <div className="row">
              <span className="dot live" aria-hidden />
              <strong>On call with {callerName}</strong>
              <span className="pill">{conversation.isSpeaking ? "AI speaking" : "Listening"}</span>
            </div>
            <span className="timer">{clock(elapsed)}</span>
          </div>
          {maxSeconds > 0 && <p className="small muted">Your balance covers up to {clock(maxSeconds)}.</p>}
          {wrapping && <p className="small">Wrapping up. The call ends after the AI says goodbye.</p>}
          <div className="row">
            <button className="secondary" onClick={requestWrapUp} disabled={wrapping}>
              Wrap up
            </button>
            <button className="secondary" onClick={() => setMuted(!muted)}>
              {muted ? "Unmute AI's ears" : "Mute AI's ears"}
            </button>
            <button className="danger" onClick={() => conversation.endSession()}>
              End now
            </button>
          </div>
          {lines.length > 0 && (
            <div className="chat">
              {lines.map((line) => (
                <div key={line.key} className={`bubble ${line.role === "agent" ? "user" : "agent"}`}>
                  {line.message}
                </div>
              ))}
            </div>
          )}
          {error && <p className="error">{error}</p>}
        </section>
      )}

      <section className="card">
        <h2>Calls with {callerName}</h2>
        {calls.filter((c) => c.conversation_id).length === 0 ? (
          <p className="muted">No calls yet.</p>
        ) : (
          <ul className="list">
            {calls
              .filter((c) => c.conversation_id)
              .map((c) => (
                <li key={c.id}>
                  <details>
                    <summary>
                      {new Date(c.created_at * 1000).toLocaleString()} · {clock(c.duration_secs)} · {usd(c.charged_micros)}
                      {c.status === "failed" && " · failed"}
                    </summary>
                    {c.topics && (
                      <p className="small">
                        <strong>Topics:</strong> {c.topics}
                      </p>
                    )}
                    {c.summary && (
                      <p className="small">
                        <strong>Summary:</strong> {c.summary}
                      </p>
                    )}
                    <div className="chat">
                      {c.transcript.map((line, i) => (
                        <div key={i} className={`bubble ${line.role === "agent" ? "user" : "agent"}`}>
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
    </>
  );
}
