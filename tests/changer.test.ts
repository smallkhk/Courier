import assert from "node:assert/strict";
import { test } from "node:test";

process.env.DATABASE_PATH = ":memory:";
process.env.CHANGER_FAST_PRICE_PER_MINUTE_USD = "0.12";
process.env.CHANGER_NATURAL_PRICE_PER_MINUTE_USD = "0.24";

const { getDb } = await import("../lib/db.ts");
const repo = await import("../lib/repo.ts");
const changer = await import("../lib/changer.ts");
const { StableWords, SpeechChunker, durationMs, toBase64, fromBase64 } = await import("../lib/changer-audio.ts");
const settings = await import("../lib/settings.ts");

function makeAccount(id: string, credit: number) {
  getDb().prepare("INSERT INTO accounts (id, credit_micros, created_at) VALUES (?, ?, 0)").run(id, credit);
}

const VOICE = "21m00Tcm4TlvDq8ikWAM"; // a built-in voice
const T0 = 1_800_000_000_000;

test("starting needs credit, an allowed voice and the switch on", () => {
  makeAccount("ch-poor", 1000);
  assert.throws(() => changer.startSession("ch-poor", "fast", VOICE), /credit/);
  makeAccount("ch-a", 1_000_000);
  assert.throws(() => changer.startSession("ch-a", "fast", "someone-elses-clone"), /voices/);
  settings.saveSetting("changerEnabled", "false");
  assert.throws(() => changer.startSession("ch-a", "fast", VOICE), /switched off/);
  settings.saveSetting("changerEnabled", "true");
});

test("fast mode prepays a minute, renews, and refunds unused time on stop", () => {
  makeAccount("ch-f", 1_000_000);
  const s = changer.startSession("ch-f", "fast", VOICE, T0);
  assert.equal(repo.getCredit("ch-f"), 1_000_000 - 120_000);
  const r = changer.renewFast("ch-f", s.id, T0 + 50_000);
  assert.equal(r.paid_until_ms, T0 + 120_000);
  assert.equal(repo.getCredit("ch-f"), 1_000_000 - 240_000);
  // Stop after 90 seconds: pay for 90s only.
  const { refundedMicros } = changer.stopSession("ch-f", s.id, T0 + 90_000);
  assert.equal(refundedMicros, 60_000);
  assert.equal(repo.getCredit("ch-f"), 1_000_000 - 180_000);
  assert.throws(() => changer.renewFast("ch-f", s.id, T0 + 95_000), /ended/);
  assert.equal(changer.stopSession("ch-f", s.id, T0 + 99_000).refundedMicros, 0);
});

test("fast mode can't renew a lapsed session or without credit", () => {
  makeAccount("ch-g", 1_000_000);
  const s = changer.startSession("ch-g", "fast", VOICE, T0);
  assert.throws(() => changer.renewFast("ch-g", s.id, T0 + 120_000), /timed out/);
  makeAccount("ch-h", 150_000);
  const h = changer.startSession("ch-h", "fast", VOICE, T0);
  assert.throws(() => changer.renewFast("ch-h", h.id, T0 + 55_000), /out of credit/);
  assert.throws(() => changer.assertFastActive("ch-h", h.id, T0 + 1000), /ended/);
});

test("sessions belong to their account", () => {
  makeAccount("ch-x", 1_000_000);
  makeAccount("ch-y", 1_000_000);
  const s = changer.startSession("ch-x", "fast", VOICE, T0);
  assert.throws(() => changer.renewFast("ch-y", s.id, T0 + 10_000));
  assert.throws(() => changer.billPhrase("ch-y", s.id, 1000));
  assert.equal(changer.stopSession("ch-y", s.id, T0 + 10_000).refundedMicros, 0);
});

test("natural mode bills phrases without rounding drift", () => {
  makeAccount("ch-n", 1_000_000);
  const s = changer.startSession("ch-n", "natural", VOICE);
  assert.equal(repo.getCredit("ch-n"), 1_000_000);
  for (let i = 0; i < 20; i++) changer.billPhrase("ch-n", s.id, 3000); // 60 seconds
  assert.equal(repo.getCredit("ch-n"), 1_000_000 - 240_000);
  assert.throws(() => changer.billPhrase("ch-n", s.id, 0) && changer.renewFast("ch-n", s.id));
});

test("stable words: only words seen twice and not last are released", () => {
  const s = new StableWords();
  assert.equal(s.onPartial("hello"), "");
  assert.equal(s.onPartial("hello how"), "hello");
  assert.equal(s.onPartial("hello how are"), "how");
  assert.equal(s.onPartial("hello how are you"), "are");
  assert.equal(s.onCommitted("Hello how are you doing?"), "you doing?");
  assert.equal(s.onPartial("next"), "");
  assert.equal(s.onCommitted("Next one."), "Next one.");
});

test("stable words: a corrected word isn't released early", () => {
  const s = new StableWords();
  s.onPartial("I want");
  assert.equal(s.onPartial("I wander"), "I");
  assert.equal(s.onPartial("I wanted to"), "");
  assert.equal(s.onPartial("I wanted to go"), "wanted to");
});

function tone(ms: number, amp: number): Int16Array {
  const samples = (16000 * ms) / 1000;
  const out = new Int16Array(samples);
  for (let i = 0; i < samples; i++) out[i] = Math.round(Math.sin(i / 5) * amp);
  return out;
}

test("speech chunker splits at pauses and keeps a little pre-roll", () => {
  const c = new SpeechChunker();
  assert.deepEqual(c.push(tone(500, 20)), []);
  assert.deepEqual(c.push(tone(800, 8000)), []);
  const out = c.push(tone(400, 20));
  assert.equal(out.length, 1);
  const ms = durationMs(out[0].length);
  assert.ok(ms >= 900 && ms <= 1320, `chunk was ${ms}ms`);
});

test("speech chunker caps long phrases and ignores tiny blips", () => {
  const c = new SpeechChunker({ maxMs: 2000 });
  assert.equal(c.push(tone(4500, 8000)).length, 2);
  const blip = new SpeechChunker();
  blip.push(tone(300, 20));
  blip.push(tone(60, 8000));
  assert.equal(blip.flush(), null);
});

test("base64 round-trips audio", () => {
  const samples = tone(100, 9000);
  assert.deepEqual(new Int16Array(fromBase64(toBase64(samples))), samples);
});
