// Runs in an AudioContext created at 16 kHz: turns mic samples into 20 ms
// frames of 16-bit PCM and reports the input level.
class MicCapture extends AudioWorkletProcessor {
  constructor() {
    super();
    this.frame = new Int16Array(320);
    this.filled = 0;
    this.peak = 0;
  }

  process(inputs) {
    const channel = inputs[0] && inputs[0][0];
    if (!channel) return true;
    for (let i = 0; i < channel.length; i++) {
      const s = Math.max(-1, Math.min(1, channel[i]));
      this.peak = Math.max(this.peak, Math.abs(s));
      this.frame[this.filled++] = s < 0 ? s * 0x8000 : s * 0x7fff;
      if (this.filled === this.frame.length) {
        this.port.postMessage({ pcm: this.frame.buffer, level: this.peak }, [this.frame.buffer]);
        this.frame = new Int16Array(320);
        this.filled = 0;
        this.peak = 0;
      }
    }
    return true;
  }
}

registerProcessor("mic-capture", MicCapture);
