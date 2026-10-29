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
  kind: "personal" as const,
  company: "",
  project: "",
  budget: "",
  stage: "",
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
  assert.match(prompt, /^You are Ada Obi/);
  assert.match(prompt, /not an assistant/);
  assert.doesNotMatch(prompt, /voice assistant to talk for/);
  assert.match(prompt, /I live in Lagos\./);
  assert.match(prompt, /Plan for dad's birthday/);
  assert.match(prompt, /Discussed school fees/);
  assert.match(prompt, /don't lie about it/);
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

test("code words tolerate spelling and small transcription slips", async () => {
  const { transcriptHasWords, matchedWords } = await import("../lib/clone-consent.ts");
  const words = ["cactus", "engine", "pocket", "candle"];
  assert.equal(transcriptHasWords("My code words are cactuses, engines, pockets and candel.", words), true);
  assert.deepEqual(matchedWords("cactus engine", words), ["cactus", "engine"]);
  assert.equal(transcriptHasWords("I like apples and oranges very much.", words), false);
  assert.equal(transcriptHasWords("", words), false);
});

test("code words stay valid for a day, including ones saved in the old format", async () => {
  const { consentWords, issueConsentScript } = await import("../lib/clone-consent.ts");
  const { getDb, kvSet } = await import("../lib/db.ts");
  getDb().prepare("INSERT INTO accounts (id, credit_micros, created_at) VALUES ('cw', 0, 0)").run();
  issueConsentScript("cw", "Ada", "CallMate");
  assert.equal(consentWords("cw")?.length, 4);
  const now = Math.floor(Date.now() / 1000);
  kvSet("clone_words:cw", JSON.stringify({ words: ["a", "b", "c", "d"], expires: now - 60 * 60 + 30 * 60 }));
  assert.deepEqual(consentWords("cw"), ["a", "b", "c", "d"]); // issued an hour ago: still fine
  kvSet("clone_words:cw", JSON.stringify({ words: ["a"], issued: now - 25 * 60 * 60 }));
  assert.equal(consentWords("cw"), null);
});

test("agent settings carry voice, turn-taking, hang-up, knowledge and action items", async () => {
  const { agentBody } = await import("../lib/elevenlabs.ts");
  const base = { prompt: "p", firstMessage: "", language: "en", voiceId: "v", maxDurationSeconds: 300 };
  const full = agentBody("a", {
    ...base,
    voice: { speed: 1.1, stability: 0.3, similarity: 0.9 },
    turn: { eagerness: "patient", timeoutSeconds: 10 },
    autoHangup: true,
    knowledge: [{ type: "url", id: "doc1", name: "Site" }],
  });
  const cc = full.conversation_config as Record<string, any>;
  assert.equal(cc.tts.speed, 1.1);
  assert.equal(cc.tts.stability, 0.3);
  assert.equal(cc.tts.similarity_boost, 0.9);
  assert.deepEqual(cc.turn, { turn_eagerness: "patient", turn_timeout: 10 });
  assert.equal(cc.agent.prompt.built_in_tools.end_call.params.system_tool_type, "end_call");
  assert.deepEqual(cc.agent.prompt.knowledge_base, [{ type: "url", id: "doc1", name: "Site", usage_mode: "auto" }]);
  assert.equal(full.platform_settings.data_collection.action_items.type, "string");
  const plain = agentBody("a", base).conversation_config as Record<string, any>;
  assert.equal(plain.agent.prompt.built_in_tools.end_call, null);
  assert.equal(plain.turn, undefined);
});

test("action items are read from the call analysis", async () => {
  const { actionItemsFrom } = await import("../lib/calls.ts");
  assert.equal(actionItemsFrom({ analysis: { data_collection_results: { action_items: { value: "- Send quote\n" } } } }), "- Send quote");
  assert.equal(actionItemsFrom({ analysis: null }), "");
  assert.equal(actionItemsFrom({ analysis: { data_collection_results: { action_items: { value: 3 } } } }), "");
});

test("the call prompt includes client details, notes and the private-note rule", () => {
  const prompt = buildCallPrompt({
    profile: { full_name: "Emmanuel", nickname: "", location: "", about: "", speaking_style: "" },
    caller: { name: "Tunde", relationship: "client", about: "", how_we_talk: "", location: "", kind: "client", company: "Tunde Foods", project: "Restaurant site", budget: "₦400k", stage: "Quote sent" },
    topics: "Follow up on the quote",
    languageCode: "en",
    localTime: "Monday 10:00",
    memory: [],
    knowledge: [{ name: "My prices", content: "Landing page ₦150k" }],
    hasDocuments: true,
    autoHangup: true,
  });
  assert.match(prompt, /Company: Tunde Foods/);
  assert.match(prompt, /Where we are: Quote sent/);
  assert.match(prompt, /My prices:\nLanding page ₦150k/);
  assert.match(prompt, /documents and website pages/);
  assert.match(prompt, /PRIVATE NOTE/);
  assert.match(prompt, /I hang up/);
});
