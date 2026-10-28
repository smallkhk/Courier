import assert from "node:assert/strict";
import { test } from "node:test";

process.env.DATABASE_PATH = ":memory:";
process.env.USDT_TRC20_ADDRESS = "TOurWalletAddress000000000000000000";
process.env.USDT_BEP20_ADDRESS = "0x00000000000000000000000000000000000000aa";

const { getDb } = await import("../lib/db.ts");
const repo = await import("../lib/repo.ts");
const payments = await import("../lib/payments.ts");
const { bscAmountToMicros, normalizeHash } = await import("../lib/chains.ts");

function makeAccount(id: string) {
  getDb().prepare("INSERT INTO accounts (id, credit_micros, created_at) VALUES (?, 0, 0)").run(id);
}

const nowSecs = () => Math.floor(Date.now() / 1000);

test("each open top-up gets a unique amount above the whole number", () => {
  makeAccount("pay-a");
  makeAccount("pay-b");
  const a = payments.createDeposit("pay-a", "trc20", 10);
  const b = payments.createDeposit("pay-b", "trc20", 10);
  assert.notEqual(a.amount_micros, b.amount_micros);
  for (const d of [a, b]) {
    assert.ok(d.amount_micros > 10_000_000 && d.amount_micros < 11_000_000);
    assert.equal(d.amount_micros % 10_000, 0);
  }
});

test("a matching transfer credits the right account once", () => {
  makeAccount("pay-c");
  const d = payments.createDeposit("pay-c", "bep20", 5);
  const transfer = {
    network: "bep20" as const,
    txHash: "0x" + "1".repeat(64),
    from: "0xsender",
    amountMicros: d.amount_micros,
    timestamp: nowSecs(),
  };
  assert.ok(payments.applyTransfer(transfer));
  assert.equal(payments.applyTransfer(transfer), null);
  assert.equal(repo.getCredit("pay-c"), d.amount_micros);
  assert.equal(repo.getDeposit("pay-c", d.id)!.status, "credited");
});

test("transfers with the wrong amount, network or time are ignored", () => {
  makeAccount("pay-d");
  const d = payments.createDeposit("pay-d", "trc20", 7);
  const base = { network: "trc20" as const, from: "Tsender", amountMicros: d.amount_micros, timestamp: nowSecs() };
  assert.equal(payments.applyTransfer({ ...base, txHash: "a".repeat(64), amountMicros: d.amount_micros - 1 }), null);
  assert.equal(payments.applyTransfer({ ...base, txHash: "b".repeat(64), network: "bep20" }), null);
  assert.equal(payments.applyTransfer({ ...base, txHash: "c".repeat(64), timestamp: nowSecs() - 3600 }), null);
  assert.equal(payments.applyTransfer({ ...base, txHash: "d".repeat(64), timestamp: nowSecs() + 7200 }), null);
  assert.equal(repo.getCredit("pay-d"), 0);
});

test("too many open top-ups are refused", () => {
  makeAccount("pay-e");
  payments.createDeposit("pay-e", "trc20", 3);
  payments.createDeposit("pay-e", "trc20", 3);
  payments.createDeposit("pay-e", "trc20", 3);
  assert.throws(() => payments.createDeposit("pay-e", "trc20", 3), payments.PaymentError);
});

test("BEP20 amounts convert from 18 decimals and reject dust", () => {
  assert.equal(bscAmountToMicros("0x" + (10_370_000n * 10n ** 12n).toString(16)), 10_370_000);
  assert.equal(bscAmountToMicros("0x" + (10_370_000n * 10n ** 12n + 1n).toString(16)), null);
});

test("hashes are normalised per network", () => {
  const hex = "AB".repeat(32);
  assert.equal(normalizeHash("trc20", hex), "ab".repeat(32));
  assert.equal(normalizeHash("trc20", "0x" + hex), "ab".repeat(32));
  assert.equal(normalizeHash("bep20", hex), "0x" + "ab".repeat(32));
  assert.equal(normalizeHash("bep20", "nope"), null);
});
