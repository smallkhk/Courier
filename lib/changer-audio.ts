// Audio helpers for the voice changer. They run in the browser (and in unit
// tests), so they use plain typed arrays and no Node APIs.

// Fast mode turns live speech-to-text into words for the voice to say.
// Partial transcripts keep changing, so a word is only spoken once it is
// "stable": it has appeared at the same position in two partials in a row and
// isn't the last (still-being-spoken) word. A committed transcript releases
// whatever is left of the sentence.
export class StableWords {
  private sent = 0;
  private previous: string[] = [];

  onPartial(text: string): string {
    const words = text.trim().split(/\s+/).filter(Boolean);
    let stable = 0;
    while (stable < words.length - 1 && stable < this.previous.length && words[stable] === this.previous[stable]) {
      stable++;
    }
    this.previous = words;
    if (stable <= this.sent) return "";
    const out = words.slice(this.sent, stable).join(" ");
    this.sent = stable;
    return out;
  }

  onCommitted(text: string): string {
    const words = text.trim().split(/\s+/).filter(Boolean);
    const out = words.slice(this.sent).join(" ");
    this.sent = 0;
    this.previous = [];
    return out;
  }
}

type ChunkerOptions = { frameMs: number; pauseMs: number; maxMs: number; minSpeechMs: number; preRollMs: number; sampleRate: number };

// Natural mode sends whole phrases to the voice changer. This splits a
// stream of 16 kHz 16-bit mono samples at short pauses (or a maximum
// length), keeping a little audio from before speech starts so first
// syllables aren't clipped.
export class SpeechChunker {
  private opts: ChunkerOptions;
  private frames: Int16Array[] = [];
  private preRoll: Int16Array[] = [];
  private speaking = false;
  private silentMs = 0;
  private speechMs = 0;
  private noiseFloor = 300;
  private pending: Int16Array = new Int16Array(0);

  constructor(opts: Partial<ChunkerOptions> = {}) {
    this.opts = { frameMs: 20, pauseMs: 350, maxMs: 4000, minSpeechMs: 200, preRollMs: 120, sampleRate: 16000, ...opts };
  }

  private get frameSamples(): number {
    return (this.opts.sampleRate * this.opts.frameMs) / 1000;
  }

  // Feeds samples in; returns any finished phrases.
  push(samples: Int16Array): Int16Array[] {
    this.pending = concat([this.pending, samples]);
    const out: Int16Array[] = [];
    while (this.pending.length >= this.frameSamples) {
      const frame = this.pending.slice(0, this.frameSamples);
      this.pending = this.pending.slice(this.frameSamples);
      const chunk = this.frame(frame);
      if (chunk) out.push(chunk);
    }
    return out;
  }

  // Ends the current phrase now (e.g. push-to-talk released).
  flush(): Int16Array | null {
    if (!this.speaking || this.speechMs < this.opts.minSpeechMs) {
      this.reset();
      return null;
    }
    const chunk = concat(this.frames);
    this.reset();
    return chunk;
  }

  private reset(): void {
    this.frames = [];
    this.speaking = false;
    this.silentMs = 0;
    this.speechMs = 0;
  }

  private frame(frame: Int16Array): Int16Array | null {
    const level = rms(frame);
    const threshold = Math.max(500, this.noiseFloor * 3);
    const loud = level > threshold;
    if (!this.speaking) {
      // Track background noise only while nobody is talking.
      if (!loud) this.noiseFloor = this.noiseFloor * 0.95 + level * 0.05;
      this.preRoll.push(frame);
      const maxPre = Math.ceil(this.opts.preRollMs / this.opts.frameMs);
      while (this.preRoll.length > maxPre) this.preRoll.shift();
      if (loud) {
        this.speaking = true;
        this.frames = [...this.preRoll];
        this.preRoll = [];
        this.speechMs = this.opts.frameMs;
        this.silentMs = 0;
      }
      return null;
    }
    this.frames.push(frame);
    if (loud) {
      this.speechMs += this.opts.frameMs;
      this.silentMs = 0;
    } else {
      this.silentMs += this.opts.frameMs;
    }
    const lengthMs = this.frames.length * this.opts.frameMs;
    if (this.silentMs >= this.opts.pauseMs || lengthMs >= this.opts.maxMs) {
      return this.flush();
    }
    return null;
  }
}

export function concat(parts: Int16Array[]): Int16Array {
  const out = new Int16Array(parts.reduce((n, p) => n + p.length, 0));
  let at = 0;
  for (const p of parts) {
    out.set(p, at);
    at += p.length;
  }
  return out;
}

export function rms(samples: Int16Array): number {
  if (samples.length === 0) return 0;
  let sum = 0;
  for (let i = 0; i < samples.length; i++) sum += samples[i] * samples[i];
  return Math.sqrt(sum / samples.length);
}

export function durationMs(samples: number, sampleRate = 16000): number {
  return (samples / sampleRate) * 1000;
}

export function toBase64(samples: Int16Array): string {
  const bytes = new Uint8Array(samples.buffer, samples.byteOffset, samples.byteLength);
  let binary = "";
  for (let i = 0; i < bytes.length; i += 0x8000) binary += String.fromCharCode(...bytes.subarray(i, i + 0x8000));
  return btoa(binary);
}

export function fromBase64(b64: string): ArrayBuffer {
  const binary = atob(b64);
  const bytes = new Uint8Array(binary.length & ~1);
  for (let i = 0; i < bytes.length; i++) bytes[i] = binary.charCodeAt(i);
  return bytes.buffer;
}
