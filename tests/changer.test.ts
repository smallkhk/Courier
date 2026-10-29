import assert from "node:assert/strict";
import { test } from "node:test";

process.env.DATABASE_PATH = ":memory:";
process.env.CHANGER_FAST_PRICE_PER_MINUTE_USD = "0.12";

const { getDb } = await import("../lib/db.ts");
const repo = await import("../lib/repo.ts");
const changer = await import("../lib/changer.ts");
const { StableWords, SpeechChunker, pcmDurationMs } = await import("../lib/changer-audio.ts");
const settings = await import("../lib/settings.ts");

function makeAccount(id: string, credit: number) {
  getDb().prepare("INSERT INTO accounts (id, credit_micros, created_at) VALUES (?, ?, 0)").run(id, credit);
}

const VOICE = "21m00Tcm4TlvDq8ikWAM"; // a built-in voice

test("tickets work once, need credit and an allowed voice", () => {
  makeAccount("ch-a", 1_000_000);
  const { ticket } = changer.createTicket("ch-a", "fast", VOICE);
  const session = changer.redeemTicket(ticket);
  assert.ok(session);
  assert.equal(session.mode, "fast");
  assert.equal(changer.redeemTicket(ticket), null);
  assert.throws(() => changer.createTicket("ch-a", "fast", "someone-elses-clone"));
  makeAccount("ch-poor", 1000);
  assert.throws(() => changer.createTicket("ch-poor", "fast", VOICE), /credit/);
  settings.saveSetting("changerEnabled", "false");
  assert.throws(() => changer.createTicket("ch-a", "fast", VOICE), /switched off/);
  settings.saveSetting("changerEnabled", "true");
});

test("blocked accounts can't redeem tickets", () => {
  makeAccount("ch-b", 1_000_000);
  const { ticket } = changer.createTicket("ch-b", "natural", VOICE);
  getDb().prepare("UPDATE accounts SET blocked_at = 1 WHERE id = 'ch-b'").run();
  assert.equal(changer.redeemTicket(ticket), null);
});

test("billing in slices adds up to the per-second price without rounding drift", () => {
  makeAccount("ch-c", 1_000_000);
  const { ticket } = changer.createTicket("ch-c", "fast", VOICE);
  const session = changer.redeemTicket(ticket)!;
  for (let i = 0; i < 12; i++) changer.billUsage(session, 5_000); // 60 seconds in 5s slices
  assert.equal(repo.getCredit("ch-c"), 1_000_000 - 120_000); // $0.12/min
  changer.billUsage(session, 333);
  assert.equal(repo.getCredit("ch-c"), 1_000_000 - changer.chargeFor("fast", 60_333));
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

function tone(ms: number, amp: number): Buffer {
  const samples = (16000 * ms) / 1000;
  const b = Buffer.alloc(samples * 2);
  for (let i = 0; i < samples; i++) b.writeInt16LE(Math.round(Math.sin(i / 5) * amp), i * 2);
  return b;
}

test("speech chunker splits at pauses and keeps a little pre-roll", () => {
  const c = new SpeechChunker();
  assert.deepEqual(c.push(tone(500, 20)), []); // background noise only
  assert.deepEqual(c.push(tone(800, 8000)), []); // speaking
  const out = c.push(tone(400, 20)); // pause ends the phrase
  assert.equal(out.length, 1);
  const ms = pcmDurationMs(out[0].length);
  assert.ok(ms >= 800 + 100 && ms <= 800 + 120 + 400, `chunk was ${ms}ms`);
});

test("speech chunker caps long phrases and ignores tiny blips", () => {
  const c = new SpeechChunker({ maxMs: 2000 });
  const out = c.push(tone(4500, 8000));
  assert.equal(out.length, 2);
  const blip = new SpeechChunker();
  blip.push(tone(300, 20));
  blip.push(tone(60, 8000));
  assert.equal(blip.flush(), null);
});
