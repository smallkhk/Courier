import assert from "node:assert/strict";
import { test } from "node:test";

process.env.DATABASE_PATH = ":memory:";
process.env.PRICE_PER_MINUTE_USD = "0.06";
process.env.SIGNUP_CREDIT_USD = "1";

const { getDb } = await import("../lib/db.ts");
const repo = await import("../lib/repo.ts");
const { voiceChargeMicros } = await import("../lib/billing.ts");
const { llmChargeMicros } = await import("../lib/claude.ts");

function makeAccount(id: string, credit: number) {
  getDb().prepare("INSERT INTO accounts (id, name, credit_micros, created_at) VALUES (?, ?, ?, 0)").run(id, id, credit);
}

test("voice is charged per second at the per-minute price", () => {
  assert.equal(voiceChargeMicros(60), 60_000);
  assert.equal(voiceChargeMicros(1), 1_000);
  assert.equal(voiceChargeMicros(0), 0);
});

test("LLM usage is charged at list price times markup", () => {
  // 1000 in * 4 + 100 out * 20 = 6000 micros, * 1.5 markup
  assert.equal(llmChargeMicros({ input_tokens: 1000, output_tokens: 100 }), 9000);
});

test("a call is billed once even if recorded twice", () => {
  makeAccount("acct-a", 1_000_000);
  const call = {
    scenarioId: "s1",
    conversationId: "conv-1",
    status: "done",
    durationSecs: 120,
    chargedMicros: voiceChargeMicros(120),
    transcript: [{ role: "agent" as const, message: "Hi" }],
    startedAt: 0,
  };
  assert.equal(repo.recordCall("acct-a", call), true);
  assert.equal(repo.recordCall("acct-a", call), false);
  assert.equal(repo.getCredit("acct-a"), 1_000_000 - 120_000);
  assert.equal(repo.listUsage("acct-a").filter((e) => e.kind === "voice").length, 1);
});

test("scenarios and transcripts are isolated per account", () => {
  makeAccount("acct-b", 0);
  makeAccount("acct-c", 0);
  const fields = { name: "Buyer", prompt: "ROLE: buyer", firstMessage: "", voiceId: "v" };
  const s = repo.insertScenario("acct-b", fields);
  assert.ok(repo.getScenario("acct-b", s.id));
  assert.equal(repo.getScenario("acct-c", s.id), undefined);
  assert.equal(repo.listScenarios("acct-c").length, 0);

  repo.updateScenario("acct-c", s.id, { ...fields, name: "Hijacked" });
  assert.equal(repo.getScenario("acct-b", s.id)!.name, "Buyer");

  repo.deleteScenario("acct-c", s.id);
  assert.ok(repo.getScenario("acct-b", s.id));

  repo.recordCall("acct-b", {
    scenarioId: s.id, conversationId: "conv-b", status: "done", durationSecs: 0,
    chargedMicros: 0, transcript: [], startedAt: 0,
  });
  assert.equal(repo.listSessions("acct-b", s.id).length, 1);
  assert.equal(repo.listSessions("acct-c", s.id).length, 0);
});
