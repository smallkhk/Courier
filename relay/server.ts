// Voice relay: the live voice changer's audio path. Runs as its own process
// next to the web app (npm run relay) because it holds long-lived websockets.
//
// Browser / desktop app  <->  relay  <->  ElevenLabs
//
// The client opens ws://relay/?ticket=... with a one-time ticket from
// /api/changer/ticket, then streams 16 kHz 16-bit mono PCM as binary frames.
// The relay sends back converted audio in the same format, plus JSON status
// messages. The ElevenLabs key never leaves the server, and usage is billed
// here as it happens.
//
// Fast mode:    mic -> realtime speech-to-text -> stable words -> streaming
//               text-to-speech in the chosen voice.
// Natural mode: mic -> phrases split at pauses -> ElevenLabs voice changer.
import { createServer } from "node:http";
import { WebSocket, WebSocketServer, type RawData } from "ws";
import { billUsage, endChangerSession, redeemTicket, type ChangerSession } from "../lib/changer.ts";
import { pcmDurationMs, SpeechChunker, StableWords } from "../lib/changer-audio.ts";
import { config } from "../lib/config.ts";

const PORT = Number(process.env.RELAY_PORT || 3200);
// ELEVENLABS_API_BASE lets tests point the relay at a local stand-in.
const EL_HTTP = `${process.env.ELEVENLABS_API_BASE || "https://api.elevenlabs.io"}/v1`;
const EL_WS = EL_HTTP.replace(/^http/, "ws");
const BILL_EVERY_MS = 5000;
const MAX_FRAME_BYTES = 64_000; // ~2 seconds of audio per message at most

function apiKey(): string {
  const key = process.env.ELEVENLABS_API_KEY;
  if (!key) throw new Error("ELEVENLABS_API_KEY is not set");
  return key;
}

type Client = {
  ws: WebSocket;
  session: ChangerSession;
  unbilledMs: number;
  closed: boolean;
};

function send(client: Client, message: Record<string, unknown>): void {
  if (client.ws.readyState === WebSocket.OPEN) client.ws.send(JSON.stringify(message));
}

function sendAudio(client: Client, pcm: Buffer): void {
  if (client.ws.readyState === WebSocket.OPEN && pcm.length) client.ws.send(pcm, { binary: true });
}

function fail(client: Client, message: string): void {
  send(client, { type: "error", message });
  client.ws.close(1011, message.slice(0, 120));
}

// Charges what has been used so far; ends the session when credit runs out.
function bill(client: Client): void {
  if (client.unbilledMs <= 0) return;
  const ms = client.unbilledMs;
  client.unbilledMs = 0;
  try {
    const credit = billUsage(client.session, ms);
    send(client, { type: "usage", creditMicros: credit });
    if (credit <= 0) fail(client, "You're out of credit. Top up to keep using the voice changer.");
  } catch (err) {
    console.error("billing failed", err);
    fail(client, "Billing error. Please try again.");
  }
}

// ------------------------------------------------------------------- fast

function startFast(client: Client): { audio: (pcm: Buffer) => void; flush: () => void; close: () => void } {
  const words = new StableWords();
  let tts: WebSocket | null = null;
  let ttsReady: Promise<void> | null = null;
  let queue: string[] = [];

  function openTts(): Promise<void> {
    const query = new URLSearchParams({
      model_id: config.ttsModel,
      output_format: "pcm_16000",
      inactivity_timeout: "180",
    });
    const socket = new WebSocket(`${EL_WS}/text-to-speech/${encodeURIComponent(client.session.voice_id)}/stream-input?${query}`, {
      headers: { "xi-api-key": apiKey() },
    });
    tts = socket;
    socket.on("message", (data: RawData) => {
      try {
        const msg = JSON.parse(data.toString()) as { audio?: string | null; isFinal?: boolean; error?: string; message?: string };
        if (msg.audio) sendAudio(client, Buffer.from(msg.audio, "base64"));
        if (msg.error || (msg.message && !msg.audio && !msg.isFinal)) console.warn("tts:", msg.error ?? msg.message);
      } catch {
        // Ignore non-JSON frames.
      }
    });
    socket.on("close", () => {
      if (tts === socket) {
        tts = null;
        ttsReady = null;
      }
    });
    socket.on("error", (err) => console.warn("tts socket error", err.message));
    return new Promise((resolve, reject) => {
      socket.once("open", () => {
        socket.send(
          JSON.stringify({
            text: " ",
            voice_settings: { stability: 0.5, similarity_boost: 0.8, style: 0, use_speaker_boost: true, speed: 1 },
            // Small first chunks so the voice starts as early as possible.
            generation_config: { chunk_length_schedule: [50, 90, 120, 150] },
          }),
        );
        resolve();
      });
      socket.once("error", reject);
    });
  }

  async function speak(text: string, flush: boolean): Promise<void> {
    if (client.closed) return;
    if (text) queue.push(text);
    if (!tts || tts.readyState > WebSocket.OPEN) ttsReady = openTts();
    try {
      await ttsReady;
    } catch (err) {
      console.warn("tts connect failed", err);
      return;
    }
    const socket = tts;
    if (!socket || socket.readyState !== WebSocket.OPEN) return;
    for (const piece of queue) socket.send(JSON.stringify({ text: `${piece} `, try_trigger_generation: true }));
    queue = [];
    if (flush) socket.send(JSON.stringify({ text: " ", flush: true }));
  }

  const sttQuery = new URLSearchParams({
    model_id: "scribe_v2_realtime",
    audio_format: "pcm_16000",
    commit_strategy: "vad",
    vad_silence_threshold_secs: "0.4",
  });
  const stt = new WebSocket(`${EL_WS}/speech-to-text/realtime?${sttQuery}`, { headers: { "xi-api-key": apiKey() } });
  let sttOpen = false;
  const backlog: Buffer[] = [];

  stt.on("open", () => {
    sttOpen = true;
    for (const pcm of backlog.splice(0)) sendChunk(pcm, false);
    send(client, { type: "ready" });
  });
  stt.on("message", (data: RawData) => {
    let msg: { message_type?: string; text?: string; error?: string };
    try {
      msg = JSON.parse(data.toString());
    } catch {
      return;
    }
    switch (msg.message_type) {
      case "partial_transcript": {
        send(client, { type: "transcript", text: msg.text ?? "", final: false });
        const ready = words.onPartial(msg.text ?? "");
        if (ready) void speak(ready, false);
        break;
      }
      case "committed_transcript": {
        send(client, { type: "transcript", text: msg.text ?? "", final: true });
        void speak(words.onCommitted(msg.text ?? ""), true);
        break;
      }
      case "session_started":
      case "committed_transcript_with_timestamps":
      case "warning":
        break;
      default:
        if (msg.error) {
          console.warn("stt error", msg.message_type, msg.error);
          if (msg.message_type === "quota_exceeded" || msg.message_type === "auth_error") {
            fail(client, "The voice service is unavailable right now.");
          }
        }
    }
  });
  stt.on("close", () => {
    if (!client.closed) fail(client, "The speech service disconnected. Start again.");
  });
  stt.on("error", (err) => console.warn("stt socket error", err.message));

  function sendChunk(pcm: Buffer, commit: boolean): void {
    stt.send(
      JSON.stringify({ message_type: "input_audio_chunk", audio_base_64: pcm.toString("base64"), commit, sample_rate: 16000 }),
    );
  }

  return {
    audio(pcm) {
      // Fast mode streams everything the mic sends, so bill all of it.
      client.unbilledMs += pcmDurationMs(pcm.length);
      if (sttOpen) sendChunk(pcm, false);
      else if (backlog.length < 100) backlog.push(pcm);
    },
    flush() {
      if (sttOpen) sendChunk(Buffer.alloc(640), true); // 20ms of silence carrying the commit
    },
    close() {
      stt.close();
      tts?.send(JSON.stringify({ text: "" }));
      tts?.close();
    },
  };
}

// ---------------------------------------------------------------- natural

function startNatural(client: Client): { audio: (pcm: Buffer) => void; flush: () => void; close: () => void } {
  const chunker = new SpeechChunker();
  let chain: Promise<void> = Promise.resolve();
  const aborter = new AbortController();

  async function convert(phrase: Buffer): Promise<void> {
    if (client.closed) return;
    // Natural mode bills the speech actually converted.
    client.unbilledMs += pcmDurationMs(phrase.length);
    const form = new FormData();
    form.set("model_id", "eleven_multilingual_sts_v2");
    form.set("file_format", "pcm_s16le_16");
    form.set("remove_background_noise", "false");
    form.append("audio", new Blob([new Uint8Array(phrase)], { type: "application/octet-stream" }), "phrase.pcm");
    const response = await fetch(
      `${EL_HTTP}/voice-changer/${encodeURIComponent(client.session.voice_id)}/stream?output_format=pcm_16000`,
      { method: "POST", headers: { "xi-api-key": apiKey() }, body: form, signal: aborter.signal },
    );
    if (!response.ok || !response.body) {
      const body = await response.text().catch(() => "");
      console.warn("voice changer failed", response.status, body.slice(0, 200));
      send(client, { type: "warning", message: "One phrase couldn't be converted." });
      return;
    }
    // Stream converted audio to the client as it arrives. Keep byte pairs
    // together so 16-bit samples are never split between messages.
    let carry = Buffer.alloc(0);
    for await (const part of response.body as unknown as AsyncIterable<Uint8Array>) {
      const data = Buffer.concat([carry, Buffer.from(part)]);
      const even = data.length - (data.length % 2);
      sendAudio(client, data.subarray(0, even));
      carry = data.subarray(even);
    }
  }

  // Phrases are converted one after another so they play in order.
  function enqueue(phrase: Buffer): void {
    send(client, { type: "phrase", ms: Math.round(pcmDurationMs(phrase.length)) });
    chain = chain.then(() => convert(phrase)).catch((err) => {
      if (!client.closed) console.warn("convert error", err);
    });
  }

  send(client, { type: "ready" });
  return {
    audio(pcm) {
      for (const phrase of chunker.push(pcm)) enqueue(phrase);
    },
    flush() {
      const phrase = chunker.flush();
      if (phrase) enqueue(phrase);
    },
    close() {
      aborter.abort();
    },
  };
}

// ----------------------------------------------------------------- server

const server = createServer((req, res) => {
  res.writeHead(req.url === "/health" ? 200 : 404, { "Content-Type": "text/plain" });
  res.end(req.url === "/health" ? "ok" : "not found");
});

const wss = new WebSocketServer({ server, maxPayload: MAX_FRAME_BYTES });

wss.on("connection", (ws, req) => {
  const ticket = new URL(req.url ?? "/", "http://relay").searchParams.get("ticket") ?? "";
  const session = ticket ? redeemTicket(ticket) : null;
  if (!session) {
    ws.send(JSON.stringify({ type: "error", message: "This session link has expired. Press Start again." }));
    ws.close(1008, "invalid ticket");
    return;
  }
  const client: Client = { ws, session, unbilledMs: 0, closed: false };
  let pipeline: ReturnType<typeof startFast>;
  try {
    pipeline = session.mode === "fast" ? startFast(client) : startNatural(client);
  } catch (err) {
    console.error(err);
    fail(client, "The voice service isn't available right now.");
    endChangerSession(session.id);
    return;
  }
  const timer = setInterval(() => bill(client), BILL_EVERY_MS);

  ws.on("message", (data: RawData, isBinary: boolean) => {
    if (isBinary) {
      const pcm = Buffer.isBuffer(data) ? data : Buffer.from(data as ArrayBuffer);
      if (pcm.length % 2 === 0) pipeline.audio(pcm);
      return;
    }
    try {
      const msg = JSON.parse(data.toString()) as { type?: string };
      if (msg.type === "flush") pipeline.flush();
    } catch {
      // Ignore malformed control messages.
    }
  });

  ws.on("close", () => {
    client.closed = true;
    clearInterval(timer);
    bill(client);
    pipeline.close();
    endChangerSession(session.id);
  });
});

server.listen(PORT, () => {
  console.log(`Voice relay listening on :${PORT}`);
});
