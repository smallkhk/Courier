"use client";

import { ConversationProvider, useConversation } from "@elevenlabs/react";
import { useRef, useState } from "react";
import type { PracticeSession } from "@/lib/repo.ts";
import { api, usd } from "./api";

type Line = { key: string; role: "user" | "agent"; message: string };
type SettleResult = { pending: number; settled: number; creditMicros: number; sessions: PracticeSession[] };

export function PracticePanel(props: {
  scenarioId: string;
  voiceReady: boolean;
  onSettled: (result: SettleResult) => void;
}) {
  return (
    <ConversationProvider>
      <Practice {...props} />
    </ConversationProvider>
  );
}

function Practice({
  scenarioId,
  voiceReady,
  onSettled,
}: {
  scenarioId: string;
  voiceReady: boolean;
  onSettled: (result: SettleResult) => void;
}) {
  const [lines, setLines] = useState<Line[]>([]);
  const [error, setError] = useState("");
  const [note, setNote] = useState("");
  const [starting, setStarting] = useState(false);
  const settling = useRef(false);

  // After a call ends ElevenLabs needs a little time to finalise it. Poll
  // until it has been billed, for up to about a minute.
  async function settle() {
    if (settling.current) return;
    settling.current = true;
    setNote("Saving your call…");
    try {
      for (let attempt = 0; attempt < 20; attempt++) {
        const result = await api<SettleResult>(`/api/scenarios/${scenarioId}/settle`, { method: "POST" });
        onSettled(result);
        // A call settled by another tab shows settled=0, so stop after a few
        // quiet rounds instead of waiting forever.
        if (result.pending === 0 && (result.settled > 0 || attempt >= 3)) {
          setNote(`Call saved. Credit left: ${usd(result.creditMicros)}.`);
          return;
        }
        await new Promise((resolve) => setTimeout(resolve, 3000));
      }
      setNote("Your call is still processing. It will show up below shortly.");
    } catch (err) {
      setNote((err as Error).message);
    } finally {
      settling.current = false;
    }
  }

  const conversation = useConversation({
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
    onDisconnect: () => void settle(),
  });

  const live = conversation.status === "connected";

  async function start() {
    setStarting(true);
    setError("");
    setNote("");
    setLines([]);
    try {
      await navigator.mediaDevices.getUserMedia({ audio: true });
      const { signedUrl } = await api<{ signedUrl: string }>(`/api/scenarios/${scenarioId}/start`, {
        method: "POST",
      });
      conversation.startSession({ signedUrl });
    } catch (err) {
      const e = err as Error;
      setError(e.name === "NotAllowedError" ? "Allow microphone access to practice." : e.message);
    } finally {
      setStarting(false);
    }
  }

  return (
    <section className="card">
      <div className="row spread">
        <div className="row">
          <span className={`dot ${live ? "live" : ""}`} aria-hidden />
          <h2 style={{ margin: 0 }}>Practice</h2>
          <span className="pill">
            {live ? (conversation.isSpeaking ? "AI speaking" : "Listening") : conversation.status}
          </span>
        </div>
        {live ? (
          <button className="danger" onClick={() => conversation.endSession()}>
            End call
          </button>
        ) : (
          <button onClick={start} disabled={!voiceReady || starting || conversation.status === "connecting"}>
            {starting || conversation.status === "connecting" ? "Connecting…" : "Start call"}
          </button>
        )}
      </div>
      {!voiceReady && (
        <p className="muted small">
          Voice isn&apos;t set up for this scenario. An admin needs to add an ElevenLabs API key, then save the
          scenario again.
        </p>
      )}
      <p className="muted small">Say &quot;end call&quot; or &quot;stop practice&quot; to finish in character, or press End call.</p>
      {error && <p className="error">{error}</p>}
      {note && <p className="small">{note}</p>}
      {lines.length > 0 && (
        <div className="chat" style={{ marginTop: 12 }}>
          {lines.map((line) => (
            <div key={line.key} className={`bubble ${line.role}`}>
              {line.message}
            </div>
          ))}
        </div>
      )}
    </section>
  );
}
