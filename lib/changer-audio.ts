// Pure audio helpers for the voice relay, kept free of I/O so they can be
// unit tested.

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

// Natural mode sends whole phrases to the voice changer. This splits a
// stream of 16 kHz 16-bit mono PCM at short pauses (or a maximum length),
// keeping a little audio from before speech starts so first syllables aren't
// clipped.
export class SpeechChunker {
  private frames: Buffer[] = [];
  private preRoll: Buffer[] = [];
  private speaking = false;
  private silentMs = 0;
  private speechMs = 0;
  private noiseFloor = 300;
  private pending = Buffer.alloc(0);

  private opts: { frameMs: number; pauseMs: number; maxMs: number; minSpeechMs: number; preRollMs: number; sampleRate: number };

  constructor(opts: Partial<SpeechChunker["opts"]> = {}) {
    this.opts = { frameMs: 20, pauseMs: 350, maxMs: 4000, minSpeechMs: 200, preRollMs: 120, sampleRate: 16000, ...opts };
  }

  private get frameBytes(): number {
    return (this.opts.sampleRate * this.opts.frameMs * 2) / 1000;
  }

  // Feeds audio in; returns any finished phrases.
  push(pcm: Buffer): Buffer[] {
    this.pending = Buffer.concat([this.pending, pcm]);
    const out: Buffer[] = [];
    while (this.pending.length >= this.frameBytes) {
      const frame = this.pending.subarray(0, this.frameBytes);
      this.pending = this.pending.subarray(this.frameBytes);
      const chunk = this.frame(Buffer.from(frame));
      if (chunk) out.push(chunk);
    }
    return out;
  }

  // Ends the current phrase now (e.g. push-to-talk released).
  flush(): Buffer | null {
    if (!this.speaking || this.speechMs < this.opts.minSpeechMs) {
      this.reset();
      return null;
    }
    const chunk = Buffer.concat(this.frames);
    this.reset();
    return chunk;
  }

  private reset(): void {
    this.frames = [];
    this.speaking = false;
    this.silentMs = 0;
    this.speechMs = 0;
  }

  private frame(frame: Buffer): Buffer | null {
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

export function rms(pcm: Buffer): number {
  const samples = pcm.length >> 1;
  if (samples === 0) return 0;
  let sum = 0;
  for (let i = 0; i < samples; i++) {
    const v = pcm.readInt16LE(i * 2);
    sum += v * v;
  }
  return Math.sqrt(sum / samples);
}

export function pcmDurationMs(bytes: number, sampleRate = 16000): number {
  return (bytes / 2 / sampleRate) * 1000;
}
