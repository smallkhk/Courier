// All reads and writes of customer data go through this file. Every function
// that touches a customer-owned table takes the caller's accountId and puts it
// in the WHERE clause, so one account can never read or change another's rows.
import { randomUUID } from "node:crypto";
import { getDb, now, transaction } from "./db.ts";

// node:sqlite returns rows with a null prototype, which React can't pass to
// client components. Copy them into plain objects.
function plain<T>(row: unknown): T {
  return { ...(row as object) } as T;
}

function plainOrUndefined<T>(row: unknown): T | undefined {
  return row === undefined ? undefined : plain<T>(row);
}

function plainAll<T>(rows: unknown[]): T[] {
  return rows.map((row) => plain<T>(row));
}

export type TranscriptLine = { role: "user" | "agent"; message: string };

export type Profile = {
  full_name: string;
  nickname: string;
  location: string;
  about: string;
  speaking_style: string;
};

export type Caller = {
  id: string;
  name: string;
  relationship: string;
  about: string;
  how_we_talk: string;
  location: string;
  timezone: string;
  language: string;
  voice_id: string;
  opening: "wait" | "greet" | "custom";
  custom_opening: string;
  created_at: number;
  updated_at: number;
};

export type CallerFields = Omit<Caller, "id" | "created_at" | "updated_at">;

export type Call = {
  id: string;
  caller_id: string | null;
  topics: string;
  status: "starting" | "done" | "failed" | "abandoned";
  conversation_id: string | null;
  duration_secs: number;
  charged_micros: number;
  transcript: TranscriptLine[];
  summary: string;
  created_at: number;
  ended_at: number | null;
};

export type SavedVoice = {
  id: string;
  voice_id: string;
  name: string;
  description: string;
  preview_url: string;
  source: "library" | "clone";
  created_at: number;
};

export type Deposit = {
  id: string;
  network: "trc20" | "bep20";
  amount_micros: number;
  status: "pending" | "credited" | "expired" | "review";
  tx_hash: string | null;
  note: string;
  created_at: number;
  expires_at: number;
  credited_at: number | null;
};

export type UsageEvent = {
  id: string;
  kind: string;
  detail: string;
  quantity: number;
  amount_micros: number;
  created_at: number;
};

// ---------------------------------------------------------------- accounts

export function getCredit(accountId: string): number {
  const row = getDb().prepare("SELECT credit_micros FROM accounts WHERE id = ?").get(accountId) as
    | { credit_micros: number }
    | undefined;
  if (!row) throw new Error("Account not found");
  return row.credit_micros;
}

export function getAgentId(accountId: string): string | null {
  const row = getDb().prepare("SELECT agent_id FROM accounts WHERE id = ?").get(accountId) as
    | { agent_id: string | null }
    | undefined;
  return row?.agent_id ?? null;
}

export function setAgentId(accountId: string, agentId: string): void {
  getDb().prepare("UPDATE accounts SET agent_id = ? WHERE id = ?").run(agentId, accountId);
}

// Adds (positive) or deducts (negative) credit and writes the ledger row.
export function applyCredit(
  accountId: string,
  amountMicros: number,
  kind: string,
  detail: string,
  quantity: number,
): void {
  transaction(() => {
    const db = getDb();
    const result = db
      .prepare("UPDATE accounts SET credit_micros = credit_micros + ? WHERE id = ?")
      .run(amountMicros, accountId);
    if (result.changes !== 1) throw new Error("Account not found");
    db.prepare(
      `INSERT INTO usage_events (id, account_id, kind, detail, quantity, amount_micros, created_at)
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
    ).run(randomUUID(), accountId, kind, detail, quantity, amountMicros, now());
  });
}

export function listUsage(accountId: string, limit = 50): UsageEvent[] {
  return plainAll<UsageEvent>(getDb()
    .prepare(
      `SELECT id, kind, detail, quantity, amount_micros, created_at FROM usage_events
       WHERE account_id = ? ORDER BY created_at DESC, rowid DESC LIMIT ?`,
    )
    .all(accountId, limit));
}

// ----------------------------------------------------------------- profile

const EMPTY_PROFILE: Profile = { full_name: "", nickname: "", location: "", about: "", speaking_style: "" };

export function getProfile(accountId: string): Profile {
  const row = getDb()
    .prepare("SELECT full_name, nickname, location, about, speaking_style FROM profiles WHERE account_id = ?")
    .get(accountId);
  return plainOrUndefined<Profile>(row) ?? { ...EMPTY_PROFILE };
}

export function saveProfile(accountId: string, profile: Profile): void {
  getDb()
    .prepare(
      `INSERT INTO profiles (account_id, full_name, nickname, location, about, speaking_style, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?)
       ON CONFLICT(account_id) DO UPDATE SET full_name = excluded.full_name, nickname = excluded.nickname,
         location = excluded.location, about = excluded.about, speaking_style = excluded.speaking_style,
         updated_at = excluded.updated_at`,
    )
    .run(accountId, profile.full_name, profile.nickname, profile.location, profile.about, profile.speaking_style, now());
}

// ----------------------------------------------------------------- callers

const CALLER_COLUMNS =
  "id, name, relationship, about, how_we_talk, location, timezone, language, voice_id, opening, custom_opening, created_at, updated_at";

export function listCallers(accountId: string): Caller[] {
  return plainAll<Caller>(getDb()
    .prepare(`SELECT ${CALLER_COLUMNS} FROM callers WHERE account_id = ? ORDER BY updated_at DESC`)
    .all(accountId));
}

export function getCaller(accountId: string, id: string): Caller | undefined {
  return plainOrUndefined<Caller>(
    getDb()
    .prepare(`SELECT ${CALLER_COLUMNS} FROM callers WHERE account_id = ? AND id = ?`)
    .get(accountId, id),
  );
}

export function insertCaller(accountId: string, f: CallerFields): Caller {
  const id = randomUUID();
  const ts = now();
  getDb()
    .prepare(
      `INSERT INTO callers (id, account_id, name, relationship, about, how_we_talk, location, timezone, language,
         voice_id, opening, custom_opening, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
    )
    .run(id, accountId, f.name, f.relationship, f.about, f.how_we_talk, f.location, f.timezone, f.language,
      f.voice_id, f.opening, f.custom_opening, ts, ts);
  return getCaller(accountId, id)!;
}

export function updateCaller(accountId: string, id: string, f: CallerFields): boolean {
  const result = getDb()
    .prepare(
      `UPDATE callers SET name = ?, relationship = ?, about = ?, how_we_talk = ?, location = ?, timezone = ?,
         language = ?, voice_id = ?, opening = ?, custom_opening = ?, updated_at = ?
       WHERE account_id = ? AND id = ?`,
    )
    .run(f.name, f.relationship, f.about, f.how_we_talk, f.location, f.timezone, f.language, f.voice_id,
      f.opening, f.custom_opening, now(), accountId, id);
  return result.changes === 1;
}

export function deleteCaller(accountId: string, id: string): void {
  transaction(() => {
    const db = getDb();
    // Keep call records for billing history, but detach them.
    db.prepare("UPDATE calls SET caller_id = NULL WHERE account_id = ? AND caller_id = ?").run(accountId, id);
    db.prepare("DELETE FROM callers WHERE account_id = ? AND id = ?").run(accountId, id);
  });
}

// ------------------------------------------------------------------- calls

type CallRow = Omit<Call, "transcript"> & { transcript: string };
const CALL_COLUMNS =
  "id, caller_id, topics, status, conversation_id, duration_secs, charged_micros, transcript, summary, created_at, ended_at";

function toCall(row: CallRow): Call {
  return { ...row, transcript: JSON.parse(row.transcript) as TranscriptLine[] };
}

export function insertCall(accountId: string, callerId: string, topics: string): Call {
  const id = randomUUID();
  getDb()
    .prepare(
      "INSERT INTO calls (id, account_id, caller_id, topics, status, created_at) VALUES (?, ?, ?, ?, 'starting', ?)",
    )
    .run(id, accountId, callerId, topics, now());
  return getCall(accountId, id)!;
}

export function getCall(accountId: string, id: string): Call | undefined {
  const row = getDb()
    .prepare(`SELECT ${CALL_COLUMNS} FROM calls WHERE account_id = ? AND id = ?`)
    .get(accountId, id) as CallRow | undefined;
  return row ? toCall(row) : undefined;
}

export function listCalls(accountId: string, opts: { callerId?: string; limit?: number } = {}): Call[] {
  const limit = opts.limit ?? 20;
  const rows = opts.callerId
    ? getDb()
        .prepare(`SELECT ${CALL_COLUMNS} FROM calls WHERE account_id = ? AND caller_id = ? ORDER BY created_at DESC LIMIT ?`)
        .all(accountId, opts.callerId, limit)
    : getDb()
        .prepare(`SELECT ${CALL_COLUMNS} FROM calls WHERE account_id = ? ORDER BY created_at DESC LIMIT ?`)
        .all(accountId, limit);
  return plainAll<CallRow>(rows).map(toCall);
}

// Summaries of the most recent finished calls with a caller, oldest first,
// so the next call can pick up where the last one ended.
export function recentSummaries(accountId: string, callerId: string, limit = 3): { when: number; summary: string }[] {
  const rows = getDb()
    .prepare(
      `SELECT created_at AS "when", summary FROM calls
       WHERE account_id = ? AND caller_id = ? AND status = 'done' AND summary != ''
       ORDER BY created_at DESC LIMIT ?`,
    )
    .all(accountId, callerId, limit) as { when: number; summary: string }[];
  return rows.reverse();
}

export function isConversationRecorded(conversationId: string): boolean {
  return getDb().prepare("SELECT 1 FROM calls WHERE conversation_id = ?").get(conversationId) !== undefined;
}

// Records a finished ElevenLabs conversation against the call it belongs to
// (the latest call started before it with no conversation yet) and deducts
// its cost, all in one transaction. Returns false if it was already recorded.
export function recordConversation(
  accountId: string,
  conv: {
    conversationId: string;
    status: "done" | "failed";
    startedAt: number;
    durationSecs: number;
    chargedMicros: number;
    transcript: TranscriptLine[];
    summary: string;
  },
): boolean {
  return transaction(() => {
    const db = getDb();
    if (isConversationRecorded(conv.conversationId)) return false;
    const target = db
      .prepare(
        `SELECT id FROM calls WHERE account_id = ? AND conversation_id IS NULL AND created_at <= ?
         ORDER BY created_at DESC LIMIT 1`,
      )
      .get(accountId, conv.startedAt + 10) as { id: string } | undefined;
    const callId = target?.id ?? randomUUID();
    if (!target) {
      db.prepare("INSERT INTO calls (id, account_id, caller_id, status, created_at) VALUES (?, ?, NULL, 'starting', ?)").run(
        callId,
        accountId,
        conv.startedAt,
      );
    }
    db.prepare(
      `UPDATE calls SET status = ?, conversation_id = ?, duration_secs = ?, charged_micros = ?, transcript = ?,
         summary = ?, ended_at = ?
       WHERE account_id = ? AND id = ?`,
    ).run(
      conv.status,
      conv.conversationId,
      conv.durationSecs,
      conv.chargedMicros,
      JSON.stringify(conv.transcript),
      conv.summary,
      conv.startedAt + conv.durationSecs,
      accountId,
      callId,
    );
    if (conv.chargedMicros > 0) {
      applyCredit(accountId, -conv.chargedMicros, "call", callId, conv.durationSecs);
    }
    return true;
  });
}

export function hasRecentStart(accountId: string, since: number): boolean {
  return (
    getDb()
      .prepare(
        "SELECT 1 FROM calls WHERE account_id = ? AND status = 'starting' AND conversation_id IS NULL AND created_at >= ?",
      )
      .get(accountId, since) !== undefined
  );
}

// Calls that were started but never connected.
export function abandonStaleCalls(accountId: string, olderThan: number): void {
  getDb()
    .prepare(
      "UPDATE calls SET status = 'abandoned' WHERE account_id = ? AND status = 'starting' AND conversation_id IS NULL AND created_at < ?",
    )
    .run(accountId, olderThan);
}

// ------------------------------------------------------------------ voices

export function listSavedVoices(accountId: string): SavedVoice[] {
  return plainAll<SavedVoice>(getDb()
    .prepare(
      "SELECT id, voice_id, name, description, preview_url, source, created_at FROM voices WHERE account_id = ? ORDER BY created_at DESC",
    )
    .all(accountId));
}

export function insertSavedVoice(
  accountId: string,
  v: { voiceId: string; name: string; description: string; previewUrl: string; source: "library" | "clone" },
): void {
  getDb()
    .prepare(
      `INSERT INTO voices (id, account_id, voice_id, name, description, preview_url, source, created_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON CONFLICT(account_id, voice_id) DO NOTHING`,
    )
    .run(randomUUID(), accountId, v.voiceId, v.name, v.description, v.previewUrl, v.source, now());
}

export function getSavedVoice(accountId: string, id: string): SavedVoice | undefined {
  return plainOrUndefined<SavedVoice>(
    getDb()
    .prepare("SELECT id, voice_id, name, description, preview_url, source, created_at FROM voices WHERE account_id = ? AND id = ?")
    .get(accountId, id),
  );
}

export function deleteSavedVoice(accountId: string, id: string): void {
  getDb().prepare("DELETE FROM voices WHERE account_id = ? AND id = ?").run(accountId, id);
}

export function countClones(accountId: string): number {
  const row = getDb()
    .prepare("SELECT COUNT(*) AS n FROM voices WHERE account_id = ? AND source = 'clone'")
    .get(accountId) as { n: number };
  return row.n;
}

// ---------------------------------------------------------------- deposits

const DEPOSIT_COLUMNS = "id, network, amount_micros, status, tx_hash, note, created_at, expires_at, credited_at";

export function listDeposits(accountId: string, limit = 20): Deposit[] {
  return plainAll<Deposit>(getDb()
    .prepare(`SELECT ${DEPOSIT_COLUMNS} FROM deposits WHERE account_id = ? ORDER BY created_at DESC LIMIT ?`)
    .all(accountId, limit));
}

export function getDeposit(accountId: string, id: string): Deposit | undefined {
  return plainOrUndefined<Deposit>(
    getDb()
    .prepare(`SELECT ${DEPOSIT_COLUMNS} FROM deposits WHERE account_id = ? AND id = ?`)
    .get(accountId, id),
  );
}
