"use client";

import { ConversationProvider, useConversation } from "@elevenlabs/react";
import { useCallback, useEffect, useRef, useState } from "react";
import { LANGUAGES } from "@/lib/languages.ts";
import type { Call } from "@/lib/repo.ts";
import type { Voice } from "@/lib/voices.ts";
import { api, usd } from "./api";
import { HangUpIcon, MicIcon, MicOffIcon, PhoneIcon, WaveHandIcon } from "./Icons";
import { Orb } from "./Orb";

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

function Transcript({ lines, callerName }: { lines: { role: "user" | "agent"; message: string; key: string | number }[]; callerName: string }) {
  return (
    <div className="chat">
      {lines.map((line, i) => {
        const me = line.role === "agent";
        const showLabel = i === 0 || lines[i - 1].role !== line.role;
        return (
          <div key={line.key} style={{ display: "contents" }}>
            {showLabel && <span className={`bubble-label ${me ? "me" : ""}`}>{me ? "Your AI" : callerName}</span>}
            <div className={`bubble ${me ? "me" : "them"}`}>{line.message}</div>
          </div>
        );
      })}
    </div>
  );
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
  const level = useCallback(() => {
    try {
      return Math.max(conversation.getOutputVolume(), conversation.getInputVolume() * 0.7);
    } catch {
      return 0;
    }
  }, [conversation]);

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
  const pastCalls = calls.filter((c) => c.conversation_id);

  if (live || connecting) {
    return (
      <section className="card card-glow callscreen">
        <div className="call-status small">
          {live ? (
            <>
              <span className="live-dot" /> On call with {callerName}
            </>
          ) : (
            <>Connecting…</>
          )}
        </div>
        <Orb getLevel={live ? level : undefined} idle={!live} />
        <div className="timer">{clock(elapsed)}</div>
        <div className="small muted">
          {!live
            ? "Getting your AI ready"
            : wrapping
              ? "Wrapping up. The call ends after the goodbye."
              : conversation.isSpeaking
                ? "Your AI is speaking"
                : `Listening to ${callerName}`}
        </div>
        {live && maxSeconds > 0 && (
          <>
            <div className="progress">
              <span style={{ width: `${Math.min(100, (elapsed / maxSeconds) * 100)}%` }} />
            </div>
            <div className="small faint" style={{ marginTop: 6 }}>
              Balance covers {clock(maxSeconds)}
            </div>
          </>
        )}
        {live && (
          <div className="call-controls">
            <span className="control-label">
              <button className="round-btn secondary" onClick={() => setMuted(!muted)} aria-label={muted ? "Unmute" : "Mute"}>
                {muted ? <MicOffIcon /> : <MicIcon />}
              </button>
              {muted ? "Unmute" : "Mute"}
            </span>
            <span className="control-label">
              <button className="round-btn" onClick={requestWrapUp} disabled={wrapping} aria-label="Wrap up">
                <WaveHandIcon />
              </button>
              Wrap up
            </span>
            <span className="control-label">
              <button className="round-btn hangup" onClick={() => conversation.endSession()} aria-label="End call">
                <HangUpIcon />
              </button>
              End
            </span>
          </div>
        )}
        {lines.length > 0 && (
          <div style={{ marginTop: 22 }}>
            <Transcript lines={lines} callerName={callerName} />
          </div>
        )}
        {error && <p className="error">{error}</p>}
      </section>
    );
  }

  return (
    <>
      <section className="card stack">
        <h2>Today&apos;s call</h2>
        {lastSummary && (
          <div className="memory">
            <strong>Last time · </strong>
            {lastSummary}
          </div>
        )}
        <div>
          <label htmlFor="topics">What should your AI talk about?</label>
          <textarea
            id="topics"
            rows={4}
            value={topics}
            onChange={(e) => setTopics(e.target.value)}
            maxLength={4000}
            placeholder={`e.g. Ask ${callerName} how the new job is going. Remind them about Dad's birthday on Saturday and ask if they can bring the cake.`}
          />
        </div>
        <div className="grid2">
          <div>
            <label htmlFor="voice">Voice</label>
            <select id="voice" value={voiceId} onChange={(e) => setVoiceId(e.target.value)}>
              {voices.map((v) => (
                <option key={v.id} value={v.id}>
                  {v.name} · {v.description}
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
        <div className="tip">
          <span>💡</span>
          <span>
            Call {callerName} from your phone and put it on <strong>speaker</strong> next to this device. Use the
            device&apos;s speakers, not headphones. When they pick up, tap Start call.
          </span>
        </div>
        {error && <p className="error">{error}</p>}
        {note && <p className="small">{note}</p>}
        <div className="row spread">
          <span className="small muted">Balance {usd(credit)}</span>
          <button className="big-btn" onClick={start}>
            <PhoneIcon size={18} /> Start call
          </button>
        </div>
      </section>

      <div className="section-head">
        <h2>Call history</h2>
        <span className="small faint">{pastCalls.length} calls</span>
      </div>
      <section className="card" style={{ marginTop: 10 }}>
        {pastCalls.length === 0 ? (
          <div className="empty">
            <span className="emoji">🎙️</span>
            No calls with {callerName} yet.
          </div>
        ) : (
          <ul className="history">
            {pastCalls.map((c) => (
              <li key={c.id}>
                <details>
                  <summary className="row spread">
                    <span>
                      <span className="chev">›</span> <strong>{new Date(c.created_at * 1000).toLocaleDateString()}</strong>{" "}
                      <span className="small muted">{new Date(c.created_at * 1000).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })}</span>
                    </span>
                    <span className="row" style={{ gap: 8 }}>
                      {c.status === "failed" && <span className="pill red">Failed</span>}
                      <span className="pill">{clock(c.duration_secs)}</span>
                      <span className="pill">{usd(c.charged_micros)}</span>
                    </span>
                  </summary>
                  <div className="stack" style={{ paddingBottom: 16 }}>
                    {c.topics && (
                      <p className="small">
                        <span className="faint">Topics · </span>
                        {c.topics}
                      </p>
                    )}
                    {c.summary && <div className="memory">{c.summary}</div>}
                    {c.transcript.length > 0 && (
                      <Transcript lines={c.transcript.map((l, i) => ({ ...l, key: i }))} callerName={callerName} />
                    )}
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
