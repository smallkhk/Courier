// Live voice changer sessions and their billing. This version needs no
// long-lived connection on our server, so it runs on shared hosting:
//
// Fast mode:    the browser talks straight to ElevenLabs using single-use
//               tokens from us. Time is prepaid in 1-minute blocks that the
//               browser renews while it runs; on stop, unused prepaid time is
//               refunded based on our own clock.
// Natural mode: the browser uploads each spoken phrase to
//               /api/changer/convert, which bills the phrase and streams the
//               converted voice back.
import { randomUUID } from "node:crypto";
import { config } from "./config.ts";
import { getDb, now, transaction } from "./db.ts";
import { HttpError } from "./errors.ts";
import { applyCredit, getCredit } from "./repo.ts";
import { isVoiceAllowed } from "./voices.ts";

export type ChangerMode = "fast" | "natural";

export const BLOCK_MS = 60_000;
// A renewal may arrive a little after the block ran out (slow network).
const RENEW_GRACE_MS = 20_000;
// Natural sessions end after this long without a phrase.
const NATURAL_IDLE_SECONDS = 15 * 60;

export type ChangerSession = {
  id: string;
  account_id: string;
  mode: ChangerMode;
  voice_id: string;
  started_ms: number;
  paid_until_ms: number | null;
  billed_ms: number;
  charged_micros: number;
  ended_at: number | null;
  last_active: number | null;
};

export function pricePerMinute(mode: ChangerMode): number {
  return mode === "fast" ? config.changerFastPerMinuteMicros : config.changerNaturalPerMinuteMicros;
}

export function chargeFor(mode: ChangerMode, ms: number): number {
  return Math.ceil((Math.max(0, ms) * pricePerMinute(mode)) / 60_000);
}

function load(accountId: string, sessionId: string): ChangerSession | undefined {
  const row = getDb()
    .prepare(
      `SELECT id, account_id, mode, voice_id, started_ms, paid_until_ms, billed_ms, charged_micros, ended_at, last_active
       FROM changer_sessions WHERE id = ? AND account_id = ?`,
    )
    .get(sessionId, accountId) as ChangerSession | undefined;
  return row ? { ...row } : undefined;
}

function end(sessionId: string): void {
  getDb().prepare("UPDATE changer_sessions SET ended_at = ? WHERE id = ? AND ended_at IS NULL").run(now(), sessionId);
}

export function startSession(accountId: string, mode: ChangerMode, voiceId: string, nowMs = Date.now()): ChangerSession {
  if (!config.changerEnabled) throw new HttpError(503, "The voice changer is switched off right now.");
  if (!isVoiceAllowed(accountId, voiceId)) throw new HttpError(400, "Pick one of your voices");
  const firstCharge = chargeFor(mode, BLOCK_MS);
  if (getCredit(accountId) < firstCharge) {
    throw new HttpError(402, "You need at least one minute of credit. Top up on the Wallet page.");
  }
  const id = randomUUID();
  transaction(() => {
    const prepaid = mode === "fast";
    getDb()
      .prepare(
        `INSERT INTO changer_sessions (id, account_id, mode, voice_id, ticket_hash, ticket_expires, started_at, started_ms,
           paid_until_ms, billed_ms, charged_micros, last_active, created_at)
         VALUES (?, ?, ?, ?, NULL, 0, ?, ?, ?, 0, ?, ?, ?)`,
      )
      .run(id, accountId, mode, voiceId, now(), nowMs, prepaid ? nowMs + BLOCK_MS : null, prepaid ? firstCharge : 0, now(), now());
    if (prepaid) applyCredit(accountId, -firstCharge, "changer", `fast ${id}`, BLOCK_MS / 1000);
  });
  return load(accountId, id)!;
}

// Fast mode: prepay the next block. Fails (and ends the session) when the
// session has lapsed or the account can't pay.
// Ends the session, then throws. Kept outside the billing transaction so
// a failed renewal can't roll back the ending.
function endAndFail(sessionId: string, status: number, message: string): never {
  end(sessionId);
  throw new HttpError(status, message);
}

export function renewFast(accountId: string, sessionId: string, nowMs = Date.now()): ChangerSession {
  const s = load(accountId, sessionId);
  if (!s || s.mode !== "fast" || s.ended_at !== null || s.paid_until_ms === null) {
    throw new HttpError(409, "This voice changer session has ended. Start again.");
  }
  if (nowMs > s.paid_until_ms + RENEW_GRACE_MS) endAndFail(s.id, 409, "This voice changer session timed out. Start again.");
  const paidUntil = Math.max(s.paid_until_ms, nowMs) + BLOCK_MS;
  const due = chargeFor("fast", paidUntil - s.started_ms) - s.charged_micros;
  if (getCredit(accountId) < due) endAndFail(s.id, 402, "You're out of credit. Top up to keep using the voice changer.");
  return transaction(() => {
    const result = getDb()
      .prepare(
        "UPDATE changer_sessions SET paid_until_ms = ?, charged_micros = charged_micros + ?, last_active = ? WHERE id = ? AND charged_micros = ? AND ended_at IS NULL",
      )
      .run(paidUntil, due, now(), s.id, s.charged_micros);
    // Another request changed the session since we read it; don't charge twice.
    if (result.changes !== 1) throw new HttpError(409, "Please try again.");
    if (due > 0) applyCredit(accountId, -due, "changer", `fast ${s.id}`, Math.round(BLOCK_MS / 1000));
    return load(accountId, sessionId)!;
  });
}

// Fast mode needs a fresh ElevenLabs token to (re)connect; only allowed
// while the session has paid time left.
export function assertFastActive(accountId: string, sessionId: string, nowMs = Date.now()): ChangerSession {
  const s = load(accountId, sessionId);
  if (!s || s.mode !== "fast" || s.ended_at !== null || s.paid_until_ms === null || nowMs > s.paid_until_ms) {
    throw new HttpError(409, "This voice changer session has ended. Start again.");
  }
  return s;
}

// Natural mode: charge one converted phrase.
export function billPhrase(accountId: string, sessionId: string, ms: number): ChangerSession {
  const s = load(accountId, sessionId);
  if (!s || s.mode !== "natural" || s.ended_at !== null) {
    throw new HttpError(409, "This voice changer session has ended. Start again.");
  }
  if ((s.last_active ?? 0) < now() - NATURAL_IDLE_SECONDS) {
    endAndFail(s.id, 409, "This voice changer session timed out. Start again.");
  }
  const billedMs = s.billed_ms + ms;
  // Charge the difference between the whole session's cost and what was
  // already charged, so per-phrase rounding never adds up.
  const due = chargeFor("natural", billedMs) - s.charged_micros;
  if (getCredit(accountId) < due) endAndFail(s.id, 402, "You're out of credit. Top up to keep using the voice changer.");
  return transaction(() => {
    const result = getDb()
      .prepare(
        "UPDATE changer_sessions SET billed_ms = ?, charged_micros = charged_micros + ?, last_active = ? WHERE id = ? AND billed_ms = ? AND ended_at IS NULL",
      )
      .run(billedMs, due, now(), s.id, s.billed_ms);
    if (result.changes !== 1) throw new HttpError(409, "Please try again.");
    if (due > 0) applyCredit(accountId, -due, "changer", `natural ${s.id}`, Math.round(ms / 1000));
    return load(accountId, sessionId)!;
  });
}

// Ends a session. Fast mode gets back the prepaid time it didn't use, as
// measured by our clock from start to now.
export function stopSession(accountId: string, sessionId: string, nowMs = Date.now()): { refundedMicros: number } {
  return transaction(() => {
    const s = load(accountId, sessionId);
    if (!s || s.ended_at !== null) return { refundedMicros: 0 };
    let refund = 0;
    if (s.mode === "fast" && s.paid_until_ms !== null) {
      const usedMs = Math.max(0, Math.min(nowMs, s.paid_until_ms) - s.started_ms);
      refund = s.charged_micros - chargeFor("fast", usedMs);
      getDb()
        .prepare("UPDATE changer_sessions SET billed_ms = ?, charged_micros = charged_micros - ? WHERE id = ?")
        .run(usedMs, Math.max(0, refund), s.id);
      if (refund > 0) applyCredit(accountId, refund, "changer", `refund unused fast ${s.id}`, 0);
    }
    end(s.id);
    return { refundedMicros: Math.max(0, refund) };
  });
}
