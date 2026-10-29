// Plays 16 kHz 16-bit PCM pushed from the main thread with a small jitter
// buffer, in an AudioContext created at 16 kHz.
class PcmPlayer extends AudioWorkletProcessor {
  constructor() {
    super();
    this.chunks = [];
    this.offset = 0;
    this.buffered = 0;
    this.playing = false;
    this.startAt = 960; // 60 ms of audio before starting, to absorb network jitter
    this.levelTick = 0;
    this.peak = 0;
    this.port.onmessage = (e) => {
      if (e.data === "clear") {
        this.chunks = [];
        this.offset = 0;
        this.buffered = 0;
        this.playing = false;
        return;
      }
      const samples = new Int16Array(e.data);
      this.chunks.push(samples);
      this.buffered += samples.length;
    };
  }

  process(_inputs, outputs) {
    const out = outputs[0][0];
    if (!this.playing && this.buffered >= this.startAt) this.playing = true;
    for (let i = 0; i < out.length; i++) {
      if (!this.playing || this.chunks.length === 0) {
        out[i] = 0;
        continue;
      }
      const chunk = this.chunks[0];
      const v = chunk[this.offset++] / 0x8000;
      out[i] = v;
      this.peak = Math.max(this.peak, Math.abs(v));
      this.buffered--;
      if (this.offset >= chunk.length) {
        this.chunks.shift();
        this.offset = 0;
      }
    }
    if (this.chunks.length === 0) this.playing = false;
    for (let c = 1; c < outputs[0].length; c++) outputs[0][c].set(out);
    if (++this.levelTick >= 8) {
      this.port.postMessage({ level: this.peak, buffered: this.buffered });
      this.levelTick = 0;
      this.peak = 0;
    }
    return true;
  }
}

registerProcessor("pcm-player", PcmPlayer);
