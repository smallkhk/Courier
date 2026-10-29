// Browser side of the live voice changer: talks to our API and ElevenLabs.
// The screen component feeds it 16 kHz mic frames and plays what comes out.
//
// Fast:    mic -> ElevenLabs realtime speech-to-text (direct websocket)
//          -> stable words -> ElevenLabs streaming text-to-speech (direct
//          websocket). Our server only hands out single-use tokens and
//          prepaid minutes, so no long connection runs through it.
// Natural: mic -> phrases split at pauses -> POST /api/changer/convert,
//          one after another, converted audio streamed back.
import { SpeechChunker, StableWords, concat, fromBase64, toBase64 } from "@/lib/changer-audio.ts";
import { api } from "./api";

export type EngineEvents = {
  onLive: () => void;
  onAudio: (pcm: ArrayBuffer) => void;
  onTranscript: (text: string) => void;
  onCredit: (micros: number) => void;
  onError: (message: string) => void;
  onEnded: () => void;
};

type FastStart = {
  sessionId: string;
  creditMicros: number;
  sttToken: string;
  ttsToken: string;
  wsBase: string;
  voiceId: string;
  ttsModel: string;
  paidUntilMs: number;
  serverNowMs: number;
};

export class ChangerEngine {
  private sessionId = "";
  private ended = false;
  private events: EngineEvents;
  private mode: "fast" | "natural";
  private voiceId: string;

  // fast
  private stt: WebSocket | null = null;
  private tts: WebSocket | null = null;
  private ttsOpening: Promise<WebSocket> | null = null;
  private words = new StableWords();
  private wsBase = "";
  private ttsModel = "";
  private renewTimer: ReturnType<typeof setTimeout> | null = null;
  private clockOffset = 0;
  private pending: Int16Array[] = [];
  private sttBacklog: Int16Array[] = [];

  // natural
  private chunker = new SpeechChunker();
  private chain: Promise<void> = Promise.resolve();
  private aborter = new AbortController();

  constructor(mode: "fast" | "natural", voiceId: string, events: EngineEvents) {
    this.mode = mode;
    this.voiceId = voiceId;
    this.events = events;
  }

  async start(): Promise<void> {
    if (this.mode === "natural") {
      const res = await api<{ sessionId: string; creditMicros: number }>("/api/changer/start", {
        method: "POST",
        body: JSON.stringify({ mode: "natural", voiceId: this.voiceId }),
      });
      this.sessionId = res.sessionId;
      this.events.onCredit(res.creditMicros);
      this.events.onLive();
      return;
    }
    const res = await api<FastStart>("/api/changer/start", {
      method: "POST",
      body: JSON.stringify({ mode: "fast", voiceId: this.voiceId }),
    });
    this.sessionId = res.sessionId;
    this.wsBase = res.wsBase;
    this.ttsModel = res.ttsModel;
    this.clockOffset = res.serverNowMs - Date.now();
    this.events.onCredit(res.creditMicros);
    this.scheduleRenew(res.paidUntilMs);
    this.ttsOpening = this.openTts(res.ttsToken);
    await this.openStt(res.sttToken);
    this.events.onLive();
  }

  // 20 ms of 16 kHz mic audio.
  audio(frame: Int16Array): void {
    if (this.ended) return;
    if (this.mode === "natural") {
      for (const phrase of this.chunker.push(frame)) this.enqueue(phrase);
      return;
    }
    this.pending.push(frame);
    if (this.pending.length < 2) return; // send every 40 ms
    const chunk = concat(this.pending);
    this.pending = [];
    if (this.stt?.readyState === WebSocket.OPEN) this.sendStt(chunk, false);
    else if (this.sttBacklog.length < 50) this.sttBacklog.push(chunk);
  }

  // End of a sentence right now (push-to-talk released).
  flush(): void {
    if (this.ended) return;
    if (this.mode === "natural") {
      const phrase = this.chunker.flush();
      if (phrase) this.enqueue(phrase);
    } else if (this.stt?.readyState === WebSocket.OPEN) {
      this.sendStt(new Int16Array(320), true);
    }
  }

  stop(reason?: string): void {
    if (this.ended) return;
    this.ended = true;
    if (reason) this.events.onError(reason);
    if (this.renewTimer) clearTimeout(this.renewTimer);
    this.aborter.abort();
    this.stt?.close();
    if (this.tts?.readyState === WebSocket.OPEN) this.tts.send(JSON.stringify({ text: "" }));
    this.tts?.close();
    if (this.sessionId) {
      const body = JSON.stringify({ sessionId: this.sessionId });
      // Keep working even if the page is closing.
      void fetch("/api/changer/stop", { method: "POST", headers: { "Content-Type": "application/json" }, body, keepalive: true })
        .then((r) => r.json())
        .then((d: { creditMicros?: number }) => typeof d.creditMicros === "number" && this.events.onCredit(d.creditMicros))
        .catch(() => {});
    }
    this.events.onEnded();
  }

  // ------------------------------------------------------------- fast mode

  private scheduleRenew(paidUntilServerMs: number): void {
    if (this.renewTimer) clearTimeout(this.renewTimer);
    const localDeadline = paidUntilServerMs - this.clockOffset;
    const wait = Math.max(1000, localDeadline - Date.now() - 15_000);
    this.renewTimer = setTimeout(() => void this.renew(), wait);
  }

  private async renew(): Promise<void> {
    if (this.ended) return;
    try {
      const res = await api<{ paidUntilMs: number; serverNowMs: number; creditMicros: number }>("/api/changer/renew", {
        method: "POST",
        body: JSON.stringify({ sessionId: this.sessionId }),
      });
      this.clockOffset = res.serverNowMs - Date.now();
      this.events.onCredit(res.creditMicros);
      this.scheduleRenew(res.paidUntilMs);
    } catch (err) {
      this.stop((err as Error).message);
    }
  }

  private async token(kind: "stt" | "tts"): Promise<string> {
    const res = await api<{ token: string }>("/api/changer/token", {
      method: "POST",
      body: JSON.stringify({ sessionId: this.sessionId, kind }),
    });
    return res.token;
  }

  private openStt(token: string): Promise<void> {
    const query = new URLSearchParams({
      model_id: "scribe_v2_realtime",
      audio_format: "pcm_16000",
      commit_strategy: "vad",
      vad_silence_threshold_secs: "0.4",
      token,
    });
    const socket = new WebSocket(`${this.wsBase}/v1/speech-to-text/realtime?${query}`);
    this.stt = socket;
    socket.onmessage = (e) => this.onStt(e.data as string);
    socket.onclose = () => {
      if (this.ended || this.stt !== socket) return;
      // Reconnect once with a fresh token (e.g. a network blip).
      this.token("stt")
        .then((t) => this.openStt(t))
        .catch(() => this.stop("Lost the connection to the speech service. Start again."));
    };
    return new Promise((resolve, reject) => {
      socket.onopen = () => {
        for (const chunk of this.sttBacklog.splice(0)) this.sendStt(chunk, false);
        resolve();
      };
      socket.onerror = () => reject(new Error("Couldn't connect to the speech service. Check your connection."));
    });
  }

  private sendStt(samples: Int16Array, commit: boolean): void {
    this.stt?.send(
      JSON.stringify({ message_type: "input_audio_chunk", audio_base_64: toBase64(samples), commit, sample_rate: 16000 }),
    );
  }

  private onStt(raw: string): void {
    let msg: { message_type?: string; text?: string; error?: string };
    try {
      msg = JSON.parse(raw);
    } catch {
      return;
    }
    if (msg.message_type === "partial_transcript") {
      this.events.onTranscript(msg.text ?? "");
      const ready = this.words.onPartial(msg.text ?? "");
      if (ready) void this.speak(ready, false);
    } else if (msg.message_type === "committed_transcript") {
      this.events.onTranscript(msg.text ?? "");
      void this.speak(this.words.onCommitted(msg.text ?? ""), true);
    } else if (msg.message_type === "quota_exceeded" || msg.message_type === "auth_error") {
      this.stop("The voice service is unavailable right now.");
    }
  }

  private openTts(token: string): Promise<WebSocket> {
    const query = new URLSearchParams({
      model_id: this.ttsModel,
      output_format: "pcm_16000",
      inactivity_timeout: "180",
      single_use_token: token,
    });
    const socket = new WebSocket(`${this.wsBase}/v1/text-to-speech/${encodeURIComponent(this.voiceId)}/stream-input?${query}`);
    this.tts = socket;
    socket.onmessage = (e) => {
      try {
        const msg = JSON.parse(e.data as string) as { audio?: string | null };
        if (msg.audio) this.events.onAudio(fromBase64(msg.audio));
      } catch {
        // ignore
      }
    };
    socket.onclose = () => {
      if (this.tts === socket) {
        this.tts = null;
        this.ttsOpening = null; // reopened with a fresh token on the next words
      }
    };
    return new Promise((resolve, reject) => {
      socket.onopen = () => {
        socket.send(
          JSON.stringify({
            text: " ",
            voice_settings: { stability: 0.5, similarity_boost: 0.8, style: 0, use_speaker_boost: true, speed: 1 },
            // Small first chunks so the voice starts as early as possible.
            generation_config: { chunk_length_schedule: [50, 90, 120, 150] },
          }),
        );
        resolve(socket);
      };
      socket.onerror = () => reject(new Error("Couldn't connect to the voice service."));
    });
  }

  private textQueue: string[] = [];

  private async speak(text: string, flush: boolean): Promise<void> {
    if (this.ended) return;
    if (text) this.textQueue.push(text);
    if (!this.ttsOpening) this.ttsOpening = this.token("tts").then((t) => this.openTts(t));
    let socket: WebSocket;
    try {
      socket = await this.ttsOpening;
    } catch {
      this.ttsOpening = null;
      return;
    }
    if (socket.readyState !== WebSocket.OPEN) return;
    for (const piece of this.textQueue.splice(0)) {
      socket.send(JSON.stringify({ text: `${piece} `, try_trigger_generation: true }));
    }
    if (flush) socket.send(JSON.stringify({ text: " ", flush: true }));
  }

  // ---------------------------------------------------------- natural mode

  // Phrases are converted one after another so they play in order.
  private enqueue(phrase: Int16Array): void {
    this.chain = this.chain.then(() => this.convert(phrase)).catch(() => {});
  }

  private async convert(phrase: Int16Array): Promise<void> {
    if (this.ended) return;
    let response: Response;
    try {
      response = await fetch(`/api/changer/convert?session=${encodeURIComponent(this.sessionId)}`, {
        method: "POST",
        headers: { "Content-Type": "application/octet-stream" },
        body: new Uint8Array(phrase.slice().buffer),
        signal: this.aborter.signal,
      });
    } catch {
      return;
    }
    if (!response.ok || !response.body) {
      const data = (await response.json().catch(() => ({}))) as { error?: string };
      if (response.status === 402 || response.status === 409) this.stop(data.error ?? "Session ended.");
      return;
    }
    // Pass audio on as it arrives, keeping 16-bit samples whole.
    const reader = response.body.getReader();
    let carry = new Uint8Array(0);
    for (;;) {
      const { value, done } = await reader.read();
      if (done || this.ended) break;
      const data = new Uint8Array(carry.length + value.length);
      data.set(carry);
      data.set(value, carry.length);
      const even = data.length - (data.length % 2);
      if (even) this.events.onAudio(data.slice(0, even).buffer);
      carry = data.slice(even);
    }
  }
}
