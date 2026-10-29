import assert from "node:assert/strict";
import { test } from "node:test";

process.env.DATABASE_PATH = ":memory:";
process.env.PRICE_PER_MINUTE_USD = "0.2";
delete process.env.USDT_TRC20_ADDRESS;

const { getDb } = await import("../lib/db.ts");
const repo = await import("../lib/repo.ts");
const admin = await import("../lib/admin.ts");
const settings = await import("../lib/settings.ts");
const { config } = await import("../lib/config.ts");
const payments = await import("../lib/payments.ts");

function makeUser(id: string, email: string, credit = 0) {
  getDb().prepare("INSERT INTO accounts (id, credit_micros, created_at) VALUES (?, ?, 0)").run(`acct-${id}`, credit);
  getDb()
    .prepare("INSERT INTO users (id, account_id, email, password_hash, created_at) VALUES (?, ?, ?, 'x', 0)")
    .run(id, `acct-${id}`, email);
}

test("settings fall back to env, save live, and reject bad values", () => {
  assert.equal(config.pricePerMinuteMicros, 200_000);
  settings.saveSetting("pricePerMinuteUsd", "0.35");
  assert.equal(config.pricePerMinuteMicros, 350_000);
  assert.throws(() => settings.validateSetting("pricePerMinuteUsd", "abc"));
  assert.throws(() => settings.validateSetting("maxCallSeconds", "30"));
  assert.throws(() => settings.validateSetting("usdtTrc20Address", "not-an-address"));
  assert.throws(() => settings.validateSetting("usdtBep20Address", "0x123"));
  assert.equal(settings.validateSetting("usdtBep20Address", "0x" + "AB".repeat(20)), "0x" + "ab".repeat(20));
  assert.equal(settings.validateSetting("usdtTrc20Address", ""), "");
  settings.saveSetting("callsEnabled", "false");
  assert.equal(config.callsEnabled, false);
  settings.saveSetting("callsEnabled", "true");
});

test("top-ups can be switched off", () => {
  makeUser("u-top", "top@x.com");
  settings.saveSetting("usdtTrc20Address", "T" + "A".repeat(33));
  settings.saveSetting("topupsEnabled", "false");
  assert.throws(() => payments.createDeposit("acct-u-top", "trc20", 5), /paused/);
  settings.saveSetting("topupsEnabled", "true");
  assert.ok(payments.createDeposit("acct-u-top", "trc20", 5));
});

test("admin credit changes hit the balance and the activity log", () => {
  makeUser("u-1", "one@x.com", 1_000_000);
  admin.adjustCredit("boss@x.com", "u-1", 5, "Refund");
  admin.adjustCredit("boss@x.com", "u-1", -2.5, "Correction");
  assert.equal(repo.getCredit("acct-u-1"), 3_500_000);
  const log = admin.listAdminLog();
  assert.ok(log.some((e) => e.action === "credit.add" && e.target === "one@x.com"));
  assert.ok(log.some((e) => e.action === "credit.remove"));
});

test("blocking signs the user out; admins can't block or demote themselves", () => {
  makeUser("u-2", "two@x.com");
  makeUser("u-admin", "boss@x.com");
  getDb().prepare("INSERT INTO sessions (token_hash, user_id, expires_at) VALUES ('t', 'u-2', 9999999999)").run();
  admin.setBlocked("boss@x.com", "u-admin", "u-2", true, "Spam");
  assert.equal(admin.getUser("u-2")!.blocked_at !== null, true);
  assert.equal((getDb().prepare("SELECT COUNT(*) AS n FROM sessions WHERE user_id = 'u-2'").get() as { n: number }).n, 0);
  admin.setBlocked("boss@x.com", "u-admin", "u-2", false, "");
  assert.equal(admin.getUser("u-2")!.blocked_at, null);
  assert.throws(() => admin.setBlocked("boss@x.com", "u-admin", "u-admin", true, ""));
  assert.throws(() => admin.setAdmin("boss@x.com", "u-admin", "u-admin", false));
  admin.setAdmin("boss@x.com", "u-admin", "u-2", true);
  assert.equal(admin.getUser("u-2")!.is_admin, 1);
});

test("manual top-up credit can't reuse a hash", () => {
  makeUser("u-3", "three@x.com");
  const d1 = payments.createDeposit("acct-u-3", "trc20", 5);
  const d2 = payments.createDeposit("acct-u-3", "trc20", 5);
  const hash = "c".repeat(64);
  payments.markDepositPaid(d1.id, 5.2, hash);
  assert.equal(repo.getCredit("acct-u-3"), 5_200_000);
  assert.throws(() => payments.markDepositPaid(d2.id, 5, hash), /already/);
  assert.throws(() => payments.markDepositPaid(d1.id, 5, ""), /already/);
  payments.rejectDeposit(d2.id, "Not found");
  assert.equal(repo.getDeposit("acct-u-3", d2.id)!.status, "rejected");
});

test("deleting a user removes their account and data", async () => {
  makeUser("u-4", "four@x.com", 1_000_000);
  repo.insertCaller("acct-u-4", {
    name: "Tunde", relationship: "", about: "", how_we_talk: "", location: "", timezone: "Africa/Lagos",
    language: "en", voice_id: "v", opening: "wait", custom_opening: "", kind: "personal", company: "", project: "", budget: "", stage: "",
  });
  await admin.deleteUser("boss@x.com", "u-admin", "u-4");
  assert.equal(admin.getUser("u-4"), undefined);
  assert.equal(repo.listCallers("acct-u-4").length, 0);
  await assert.rejects(admin.deleteUser("boss@x.com", "u-admin", "u-admin"));
});

test("stats add up", () => {
  const s = admin.getStats();
  assert.ok(s.users >= 5);
  assert.equal(s.daily.length, 14);
  assert.ok(s.depositedMicros >= 5_200_000);
});
