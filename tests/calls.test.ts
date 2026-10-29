import assert from "node:assert/strict";
import { test } from "node:test";

process.env.DATABASE_PATH = ":memory:";
process.env.PRICE_PER_MINUTE_USD = "0.2";
process.env.MAX_CALL_SECONDS = "1800";

const { getDb } = await import("../lib/db.ts");
const repo = await import("../lib/repo.ts");
const { allowedSeconds, callChargeMicros } = await import("../lib/calls.ts");
const { buildCallPrompt, timeGreeting } = await import("../lib/call-prompt.ts");
const { transcriptHasWords } = await import("../lib/clone-consent.ts");

function makeAccount(id: string, credit = 0) {
  getDb().prepare("INSERT INTO accounts (id, credit_micros, created_at) VALUES (?, ?, 0)").run(id, credit);
}

const callerFields = {
  name: "Tunde",
  relationship: "brother",
  about: "",
  how_we_talk: "",
  location: "",
  timezone: "Africa/Lagos",
  language: "en",
  voice_id: "v",
  opening: "wait" as const,
  custom_opening: "",
};

test("calls are charged per second and capped by balance", () => {
  assert.equal(callChargeMicros(60), 200_000);
  assert.equal(callChargeMicros(1), 3_334);
  assert.equal(allowedSeconds(1_000_000), 300); // $1 at $0.20/min
  assert.equal(allowedSeconds(100_000_000), 1800); // capped
  assert.equal(allowedSeconds(-5), 0);
});

test("a conversation is recorded against the latest started call and billed once", () => {
  makeAccount("call-a", 1_000_000);
  const caller = repo.insertCaller("call-a", callerFields);
  const call = repo.insertCall("call-a", caller.id, "Ask about mum");
  const conv = {
    conversationId: "conv-1",
    status: "done" as const,
    startedAt: call.created_at + 2,
    durationSecs: 90,
    chargedMicros: callChargeMicros(90),
    transcript: [{ role: "user" as const, message: "Hello?" }],
    summary: "Talked about mum.",
  };
  assert.equal(repo.recordConversation("call-a", conv), true);
  assert.equal(repo.recordConversation("call-a", conv), false);
  assert.equal(repo.getCredit("call-a"), 1_000_000 - 300_000);
  const saved = repo.getCall("call-a", call.id)!;
  assert.equal(saved.status, "done");
  assert.equal(saved.summary, "Talked about mum.");
  assert.deepEqual(
    repo.recentSummaries("call-a", caller.id).map((s) => s.summary),
    ["Talked about mum."],
  );
});

test("accounts can't see or change each other's callers, calls or voices", () => {
  makeAccount("iso-a");
  makeAccount("iso-b");
  const caller = repo.insertCaller("iso-a", callerFields);
  repo.insertCall("iso-a", caller.id, "x");
  repo.insertSavedVoice("iso-a", { voiceId: "clone-1", name: "Me", description: "", previewUrl: "", source: "clone" });

  assert.equal(repo.getCaller("iso-b", caller.id), undefined);
  assert.equal(repo.listCallers("iso-b").length, 0);
  assert.equal(repo.updateCaller("iso-b", caller.id, { ...callerFields, name: "Hijacked" }), false);
  repo.deleteCaller("iso-b", caller.id);
  assert.equal(repo.getCaller("iso-a", caller.id)!.name, "Tunde");
  assert.equal(repo.listCalls("iso-b").length, 0);
  assert.equal(repo.listSavedVoices("iso-b").length, 0);
});

test("the call prompt speaks as the owner and keeps the guardrails", () => {
  const prompt = buildCallPrompt({
    profile: { full_name: "Ada Obi", nickname: "Sis", location: "Lagos", about: "Works in tech.", speaking_style: "" },
    caller: { name: "Tunde", relationship: "brother", about: "", how_we_talk: "Pidgin and jokes", location: "Abuja" },
    topics: "Plan for dad's birthday",
    languageCode: "en",
    localTime: "Sunday 18:00",
    memory: [{ when: "2026-10-20", summary: "Discussed school fees." }],
  });
  assert.match(prompt, /speaking as Ada Obi/);
  assert.match(prompt, /Plan for dad's birthday/);
  assert.match(prompt, /Discussed school fees/);
  assert.match(prompt, /don't deny it/);
  assert.match(prompt, /one-time codes/);
});

test("greeting follows the caller's time zone", () => {
  const noonUtc = new Date("2026-10-28T12:00:00Z");
  assert.equal(timeGreeting("Africa/Lagos", noonUtc), "Good afternoon");
  assert.equal(timeGreeting("America/New_York", noonUtc), "Good morning");
  assert.equal(timeGreeting("Asia/Tokyo", noonUtc), "Good evening");
});

test("clone consent needs at least three of the four code words", () => {
  const words = ["river", "orange", "candle", "pepper"];
  assert.equal(transcriptHasWords("My code words are river, orange, candle and pepper.", words), true);
  assert.equal(transcriptHasWords("river orange candle", words), true);
  assert.equal(transcriptHasWords("river orange", words), false);
});

test("English agents get the English voice model, others the multilingual one", async () => {
  const { agentTtsModel } = await import("../lib/elevenlabs.ts");
  assert.equal(agentTtsModel("eleven_flash_v2_5", "en"), "eleven_flash_v2");
  assert.equal(agentTtsModel("eleven_turbo_v2_5", "en"), "eleven_turbo_v2");
  assert.equal(agentTtsModel("eleven_flash_v2_5", "fr"), "eleven_flash_v2_5");
  assert.equal(agentTtsModel("eleven_flash_v2", "fr"), "eleven_flash_v2_5");
  assert.equal(agentTtsModel("eleven_v4_turbo", "en"), "eleven_v4_turbo");
});
