// Live voice changer sessions and their billing. The web app creates a
// session with a one-time ticket; the voice relay (relay/server.ts) redeems
// the ticket, streams audio, and bills the account as it goes.
import { createHash, randomBytes, randomUUID } from "node:crypto";
import { config } from "./config.ts";
import { getDb, now, transaction } from "./db.ts";
import { HttpError } from "./errors.ts";
import { applyCredit, getCredit } from "./repo.ts";
import { isVoiceAllowed } from "./voices.ts";

export type ChangerMode = "fast" | "natural";

const TICKET_SECONDS = 60;

function hashTicket(ticket: string): string {
  return createHash("sha256").update(ticket).digest("hex");
}

export function pricePerMinute(mode: ChangerMode): number {
  return mode === "fast" ? config.changerFastPerMinuteMicros : config.changerNaturalPerMinuteMicros;
}

export function chargeFor(mode: ChangerMode, ms: number): number {
  return Math.ceil((Math.max(0, ms) * pricePerMinute(mode)) / 60_000);
}

export function createTicket(accountId: string, mode: ChangerMode, voiceId: string): { ticket: string; sessionId: string } {
  if (!config.changerEnabled) throw new HttpError(503, "The voice changer is switched off right now.");
  if (!isVoiceAllowed(accountId, voiceId)) throw new HttpError(400, "Pick one of your voices");
  if (getCredit(accountId) < chargeFor(mode, 60_000)) {
    throw new HttpError(402, "You need at least one minute of credit. Top up on the Wallet page.");
  }
  const ticket = randomBytes(32).toString("base64url");
  const sessionId = randomUUID();
  getDb()
    .prepare(
      `INSERT INTO changer_sessions (id, account_id, mode, voice_id, ticket_hash, ticket_expires, created_at)
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
    )
    .run(sessionId, accountId, mode, voiceId, hashTicket(ticket), now() + TICKET_SECONDS, now());
  return { ticket, sessionId };
}

export type ChangerSession = { id: string; account_id: string; mode: ChangerMode; voice_id: string };

// Exchanges a ticket for its session. Tickets work once and expire quickly.
export function redeemTicket(ticket: string): ChangerSession | null {
  return transaction(() => {
    const db = getDb();
    const session = db
      .prepare(
        `SELECT s.id, s.account_id, s.mode, s.voice_id FROM changer_sessions s JOIN accounts a ON a.id = s.account_id
         WHERE s.ticket_hash = ? AND s.ticket_expires >= ? AND s.started_at IS NULL AND a.blocked_at IS NULL`,
      )
      .get(hashTicket(ticket), now()) as ChangerSession | undefined;
    if (!session) return null;
    db.prepare("UPDATE changer_sessions SET ticket_hash = NULL, started_at = ? WHERE id = ?").run(now(), session.id);
    return { ...session };
  });
}

// Adds usage to a session and charges for it. Charges are rounded up per
// call, so billing in small slices costs slightly more than one big slice;
// the relay bills about every 5 seconds to keep that negligible. Returns the
// account's credit left.
export function billUsage(session: ChangerSession, ms: number): number {
  if (ms <= 0) return getCredit(session.account_id);
  return transaction(() => {
    const db = getDb();
    const row = db.prepare("SELECT billed_ms, charged_micros FROM changer_sessions WHERE id = ?").get(session.id) as {
      billed_ms: number;
      charged_micros: number;
    };
    const billedMs = row.billed_ms + ms;
    // Charge the difference between what the whole session costs now and
    // what has already been charged, so rounding never adds up.
    const due = chargeFor(session.mode, billedMs) - row.charged_micros;
    db.prepare("UPDATE changer_sessions SET billed_ms = ?, charged_micros = charged_micros + ? WHERE id = ?").run(
      billedMs,
      due,
      session.id,
    );
    if (due > 0) applyCredit(session.account_id, -due, "changer", `${session.mode} ${session.id}`, Math.round(ms / 1000));
    return getCredit(session.account_id);
  });
}

export function endChangerSession(sessionId: string): void {
  getDb().prepare("UPDATE changer_sessions SET ended_at = ? WHERE id = ? AND ended_at IS NULL").run(now(), sessionId);
}

export function relayUrlFor(requestHost: string | null, secure: boolean): string {
  if (config.relayUrl) return config.relayUrl;
  const host = (requestHost ?? "localhost").replace(/:\d+$/, "");
  return `${secure ? "wss" : "ws"}://${host}:${process.env.RELAY_PORT || 3200}`;
}
