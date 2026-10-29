"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import type { Voice } from "@/lib/voices.ts";
import { api, usd } from "./api";
import { MicIcon } from "./Icons";
import { Orb } from "./Orb";

type Mode = "fast" | "natural";
type Status = "off" | "connecting" | "live";

declare global {
  interface Window {
    // Provided by the desktop app's preload script.
    callmateDesktop?: {
      isDesktop: true;
      onTalkToggle: (cb: () => void) => () => void;
      openExternal: (url: string) => void;
      hotkey: string;
    };
  }
  interface AudioContext {
    setSinkId?: (id: string) => Promise<void>;
  }
}

const MODES: { key: Mode; name: string; note: string }[] = [
  { key: "fast", name: "⚡ Fast", note: "~0.5–1s · same words, new voice" },
  { key: "natural", name: "🎭 Natural", note: "~1–2s · keeps your tone & emotion" },
];

const VB_CABLE_URL = "https://vb-audio.com/Cable/";

function Meter({ level }: { level: number }) {
  return (
    <div className="progress" style={{ maxWidth: "none", margin: "6px 0 0" }}>
      <span style={{ width: `${Math.min(100, Math.round(Math.sqrt(level) * 100))}%`, transition: "width 0.08s linear" }} />
    </div>
  );
}

export function VoiceChanger({
  voices,
  initialCredit,
  prices,
  enabled,
}: {
  voices: Voice[];
  initialCredit: number;
  prices: Record<Mode, number>;
  enabled: boolean;
}) {
  const [mode, setMode] = useState<Mode>("fast");
  const [voiceId, setVoiceId] = useState(voices[0]?.id ?? "");
  const [inputs, setInputs] = useState<MediaDeviceInfo[]>([]);
  const [outputs, setOutputs] = useState<MediaDeviceInfo[]>([]);
  const [micId, setMicId] = useState("");
  const [outId, setOutId] = useState("");
  const [monitor, setMonitor] = useState(false);
  const [pushToTalk, setPushToTalk] = useState(false);
  const [talking, setTalking] = useState(false);
  const [status, setStatus] = useState<Status>("off");
  const [error, setError] = useState("");
  const [warning, setWarning] = useState("");
  const [credit, setCredit] = useState(initialCredit);
  const [inLevel, setInLevel] = useState(0);
  const [outLevel, setOutLevel] = useState(0);
  const [transcript, setTranscript] = useState("");
  const [isDesktop, setIsDesktop] = useState(false);

  const ws = useRef<WebSocket | null>(null);
  const micCtx = useRef<AudioContext | null>(null);
  const stream = useRef<MediaStream | null>(null);
  const players = useRef<{ ctx: AudioContext; node: AudioWorkletNode }[]>([]);
  const outLevelRef = useRef(0);
  const talkingRef = useRef(false);
  const pttRef = useRef(false);
  const batch = useRef<Int16Array[]>([]);

  const cable = outputs.find((d) => /cable input|vb-audio|blackhole/i.test(d.label));

  const loadDevices = useCallback(async () => {
    const all = await navigator.mediaDevices.enumerateDevices();
    setInputs(all.filter((d) => d.kind === "audioinput"));
    setOutputs(all.filter((d) => d.kind === "audiooutput"));
  }, []);

  useEffect(() => {
    setIsDesktop(Boolean(window.callmateDesktop));
    void loadDevices();
    navigator.mediaDevices.addEventListener("devicechange", loadDevices);
    return () => navigator.mediaDevices.removeEventListener("devicechange", loadDevices);
  }, [loadDevices]);

  // Default the output to the virtual cable when one is installed.
  useEffect(() => {
    if (!outId && cable) setOutId(cable.deviceId);
  }, [cable, outId]);

  useEffect(() => {
    pttRef.current = pushToTalk;
  }, [pushToTalk]);

  const setTalk = useCallback((on: boolean) => {
    if (talkingRef.current === on) return;
    talkingRef.current = on;
    setTalking(on);
    if (!on && ws.current?.readyState === WebSocket.OPEN) ws.current.send(JSON.stringify({ type: "flush" }));
  }, []);

  // Push-to-talk: hold Space in the window, or the desktop app's hotkey toggles.
  useEffect(() => {
    if (!pushToTalk) return;
    const down = (e: KeyboardEvent) => {
      if (e.code === "Space" && !(e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement)) {
        e.preventDefault();
        setTalk(true);
      }
    };
    const up = (e: KeyboardEvent) => {
      if (e.code === "Space") setTalk(false);
    };
    window.addEventListener("keydown", down);
    window.addEventListener("keyup", up);
    const off = window.callmateDesktop?.onTalkToggle(() => setTalk(!talkingRef.current));
    return () => {
      window.removeEventListener("keydown", down);
      window.removeEventListener("keyup", up);
      off?.();
    };
  }, [pushToTalk, setTalk]);

  const stop = useCallback(() => {
    ws.current?.close();
    ws.current = null;
    stream.current?.getTracks().forEach((t) => t.stop());
    stream.current = null;
    void micCtx.current?.close();
    micCtx.current = null;
    for (const p of players.current) void p.ctx.close();
    players.current = [];
    batch.current = [];
    setStatus("off");
    setInLevel(0);
    setOutLevel(0);
    outLevelRef.current = 0;
  }, []);

  useEffect(() => stop, [stop]);

  async function makePlayer(sinkId: string): Promise<{ ctx: AudioContext; node: AudioWorkletNode }> {
    const ctx = new AudioContext({ sampleRate: 16000, latencyHint: "interactive" });
    if (sinkId && ctx.setSinkId) await ctx.setSinkId(sinkId);
    await ctx.audioWorklet.addModule("/audio/player-worklet.js");
    const node = new AudioWorkletNode(ctx, "pcm-player", { outputChannelCount: [2] });
    node.connect(ctx.destination);
    return { ctx, node };
  }

  async function start() {
    setError("");
    setWarning("");
    setTranscript("");
    setStatus("connecting");
    try {
      if (typeof AudioContext === "undefined" || !("audioWorklet" in AudioContext.prototype)) {
        throw new Error("Your browser can't do live audio. Use Chrome, Edge or the desktop app.");
      }
      const media = await navigator.mediaDevices.getUserMedia({
        audio: {
          deviceId: micId ? { exact: micId } : undefined,
          echoCancellation: true,
          noiseSuppression: true,
          autoGainControl: true,
          channelCount: 1,
        },
      });
      stream.current = media;
      await loadDevices(); // labels become visible after permission

      const { ticket, relayUrl } = await api<{ ticket: string; relayUrl: string }>("/api/changer/ticket", {
        method: "POST",
        body: JSON.stringify({ mode, voiceId }),
      });

      // Output: the chosen device (e.g. the virtual cable), plus speakers if monitoring.
      players.current = [await makePlayer(outId)];
      if (monitor && outId) players.current.push(await makePlayer(""));
      players.current[0].node.port.onmessage = (e) => {
        outLevelRef.current = e.data.level;
        setOutLevel(e.data.level);
      };

      const socket = new WebSocket(`${relayUrl}/?ticket=${encodeURIComponent(ticket)}`);
      socket.binaryType = "arraybuffer";
      ws.current = socket;
      socket.onmessage = (e) => {
        if (typeof e.data !== "string") {
          for (const p of players.current) {
            const copy = (e.data as ArrayBuffer).slice(0);
            p.node.port.postMessage(copy, [copy]);
          }
          return;
        }
        const msg = JSON.parse(e.data) as { type: string; message?: string; text?: string; final?: boolean; creditMicros?: number };
        if (msg.type === "ready") setStatus("live");
        if (msg.type === "error") setError(msg.message ?? "Something went wrong");
        if (msg.type === "warning") setWarning(msg.message ?? "");
        if (msg.type === "usage" && typeof msg.creditMicros === "number") setCredit(msg.creditMicros);
        if (msg.type === "transcript") setTranscript(msg.text ?? "");
      };
      socket.onclose = () => stop();
      socket.onerror = () => setError("Couldn't reach the voice relay. Check your connection and try again.");

      // Input: the mic, at 16 kHz, in 20 ms frames, sent every 40 ms.
      const ctx = new AudioContext({ sampleRate: 16000, latencyHint: "interactive" });
      micCtx.current = ctx;
      await ctx.audioWorklet.addModule("/audio/mic-worklet.js");
      const source = ctx.createMediaStreamSource(media);
      const node = new AudioWorkletNode(ctx, "mic-capture");
      node.port.onmessage = (e) => {
        setInLevel(e.data.level);
        const open = socket.readyState === WebSocket.OPEN;
        const sending = !pttRef.current || talkingRef.current;
        if (!open || !sending) return;
        batch.current.push(new Int16Array(e.data.pcm));
        if (batch.current.length >= 2) {
          const merged = new Int16Array(batch.current.reduce((n, f) => n + f.length, 0));
          let at = 0;
          for (const f of batch.current) {
            merged.set(f, at);
            at += f.length;
          }
          batch.current = [];
          socket.send(merged.buffer);
        }
      };
      source.connect(node);
    } catch (err) {
      const e = err as Error;
      setError(e.name === "NotAllowedError" ? "Allow microphone access to use the voice changer." : e.message);
      stop();
    }
  }

  const live = status === "live";
  const getOut = useCallback(() => outLevelRef.current, []);

  if (!enabled) {
    return (
      <section className="card empty">
        <span className="emoji">🛠️</span>
        The voice changer is switched off right now. Please check back soon.
      </section>
    );
  }

  return (
    <>
      <section className="card card-glow callscreen">
        <div className="call-status small">
          {live ? (
            <>
              <span className="live-dot" /> Voice changer on
            </>
          ) : status === "connecting" ? (
            "Connecting…"
          ) : (
            "Voice changer off"
          )}
        </div>
        <Orb getLevel={live ? getOut : undefined} idle={!live} />
        {live && pushToTalk && (
          <div className={`pill ${talking ? "green" : ""}`} style={{ marginBottom: 8 }}>
            {talking ? "🎙 Talking" : isDesktop ? `Press ${window.callmateDesktop?.hotkey} or hold Space to talk` : "Hold Space to talk"}
          </div>
        )}
        {live && mode === "fast" && transcript && <p className="small muted">“{transcript}”</p>}
        <div className="grid2" style={{ textAlign: "left", marginTop: 10 }}>
          <div>
            <span className="small muted">Your mic</span>
            <Meter level={inLevel} />
          </div>
          <div>
            <span className="small muted">New voice out</span>
            <Meter level={outLevel} />
          </div>
        </div>
        <div className="call-controls">
          {live || status === "connecting" ? (
            <button className="big-btn round-btn hangup" style={{ width: "auto", height: "auto", borderRadius: 999, padding: "14px 28px", flexDirection: "row" }} onClick={stop}>
              Stop
            </button>
          ) : (
            <button className="big-btn" onClick={start} disabled={!voiceId}>
              <MicIcon size={18} /> Start voice changer
            </button>
          )}
        </div>
        {error && <p className="error small" style={{ marginTop: 12 }}>{error}</p>}
        {warning && <p className="small muted">{warning}</p>}
        <p className="small faint" style={{ marginTop: 10 }}>
          Balance {usd(credit)} · {usd(prices[mode])}/min in {mode === "fast" ? "Fast" : "Natural"} mode
        </p>
      </section>

      <section className="card stack">
        <h2>Settings</h2>
        <div>
          <label>Mode</label>
          <div className="net-toggle">
            {MODES.map((m) => (
              <button key={m.key} type="button" className={mode === m.key ? "active" : ""} disabled={status !== "off"} onClick={() => setMode(m.key)}>
                {m.name}
                <small>{m.note}</small>
              </button>
            ))}
          </div>
        </div>
        <div className="grid2">
          <div>
            <label htmlFor="cv-voice">Voice</label>
            <select id="cv-voice" value={voiceId} onChange={(e) => setVoiceId(e.target.value)} disabled={status !== "off"}>
              {voices.map((v) => (
                <option key={v.id} value={v.id}>
                  {v.name} · {v.description}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label htmlFor="cv-mic">Microphone</label>
            <select id="cv-mic" value={micId} onChange={(e) => setMicId(e.target.value)} disabled={status !== "off"}>
              <option value="">Default microphone</option>
              {inputs
                .filter((d) => d.deviceId && d.deviceId !== "default")
                .map((d) => (
                  <option key={d.deviceId} value={d.deviceId}>
                    {d.label || "Microphone"}
                  </option>
                ))}
            </select>
          </div>
        </div>
        <div>
          <label htmlFor="cv-out">Send the new voice to</label>
          <select id="cv-out" value={outId} onChange={(e) => setOutId(e.target.value)} disabled={status !== "off"}>
            <option value="">Speakers (just listen)</option>
            {outputs
              .filter((d) => d.deviceId && d.deviceId !== "default")
              .map((d) => (
                <option key={d.deviceId} value={d.deviceId}>
                  {d.label || "Output device"}
                  {d === cable ? " ← virtual mic" : ""}
                </option>
              ))}
          </select>
        </div>
        <div className="row spread">
          <div>
            <label style={{ marginBottom: 0 }}>Hear the new voice too</label>
            <div className="small muted">Also plays it on your speakers or headphones.</div>
          </div>
          <div className="toggle">
            <button type="button" className={monitor ? "on" : ""} disabled={status !== "off"} onClick={() => setMonitor(true)}>
              On
            </button>
            <button type="button" className={!monitor ? "on" : ""} disabled={status !== "off"} onClick={() => setMonitor(false)}>
              Off
            </button>
          </div>
        </div>
        <div className="row spread">
          <div>
            <label style={{ marginBottom: 0 }}>Push to talk</label>
            <div className="small muted">
              Only converts while you hold Space{isDesktop ? ` (or press ${window.callmateDesktop?.hotkey} anywhere)` : ""}. Saves credit.
            </div>
          </div>
          <div className="toggle">
            <button type="button" className={pushToTalk ? "on" : ""} onClick={() => setPushToTalk(true)}>
              On
            </button>
            <button type="button" className={!pushToTalk ? "on" : ""} onClick={() => setPushToTalk(false)}>
              Off
            </button>
          </div>
        </div>
      </section>

      <section className="card stack">
        <h2>Use it in WhatsApp, Zoom, Discord and more</h2>
        {cable ? (
          <div className="memory">
            <strong>✓ Virtual mic found:</strong> {cable.label}. Pick it above, then in the other app choose{" "}
            <strong>&quot;CABLE Output&quot;</strong> as the microphone.
          </div>
        ) : (
          <div className="tip">
            <span>🎛️</span>
            <span>
              Install the free <strong>VB-Audio Virtual Cable</strong> once. It adds a virtual microphone to Windows.{" "}
              <a
                href={VB_CABLE_URL}
                target="_blank"
                rel="noreferrer"
                onClick={(e) => {
                  if (window.callmateDesktop) {
                    e.preventDefault();
                    window.callmateDesktop.openExternal(VB_CABLE_URL);
                  }
                }}
              >
                Download VB-Cable ↗
              </a>
            </span>
          </div>
        )}
        <ol className="small muted" style={{ paddingLeft: 18, margin: 0 }}>
          <li>Install VB-Cable (run the installer as administrator, then restart your PC).</li>
          <li>
            Here, set <strong>Send the new voice to</strong> → <strong>CABLE Input</strong>.
          </li>
          <li>
            In WhatsApp, Zoom, Discord, Telegram or OBS, set the <strong>microphone</strong> to <strong>CABLE Output</strong>.
          </li>
          <li>Press Start voice changer and talk. They hear the new voice.</li>
        </ol>
        {!isDesktop && (
          <p className="small muted">
            Tip: the Windows desktop app keeps running in the background and has a global talk hotkey.
          </p>
        )}
      </section>
    </>
  );
}
