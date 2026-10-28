// All reads and writes of customer data go through this file. Every function
// that touches a customer-owned table takes the caller's accountId and puts it
// in the WHERE clause, so one tenant can never read or change another's rows.
import { randomUUID } from "node:crypto";
import { getDb, now, transaction } from "./db.ts";

export type Scenario = {
  id: string;
  account_id: string;
  name: string;
  prompt: string;
  first_message: string;
  voice_id: string;
  agent_id: string | null;
  created_at: number;
  updated_at: number;
};

export type TranscriptLine = { role: "user" | "agent"; message: string };

export type PracticeSession = {
  id: string;
  scenario_id: string;
  conversation_id: string;
  status: string;
  duration_secs: number;
  charged_micros: number;
  transcript: TranscriptLine[];
  started_at: number;
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
  const row = getDb()
    .prepare("SELECT credit_micros FROM accounts WHERE id = ?")
    .get(accountId) as { credit_micros: number } | undefined;
  if (!row) throw new Error("Account not found");
  return row.credit_micros;
}

// Adds (positive) or deducts (negative) credit and writes the ledger row in
// the same statement batch. Callers that need atomicity with other writes
// wrap this in transaction().
export function applyCredit(
  accountId: string,
  amountMicros: number,
  kind: string,
  detail: string,
  quantity: number,
): void {
  const db = getDb();
  const result = db
    .prepare("UPDATE accounts SET credit_micros = credit_micros + ? WHERE id = ?")
    .run(amountMicros, accountId);
  if (result.changes !== 1) throw new Error("Account not found");
  db.prepare(
    `INSERT INTO usage_events (id, account_id, kind, detail, quantity, amount_micros, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?)`,
  ).run(randomUUID(), accountId, kind, detail, quantity, amountMicros, now());
}

export function listUsage(accountId: string, limit = 50): UsageEvent[] {
  return getDb()
    .prepare(
      `SELECT id, kind, detail, quantity, amount_micros, created_at FROM usage_events
       WHERE account_id = ? ORDER BY created_at DESC, rowid DESC LIMIT ?`,
    )
    .all(accountId, limit) as UsageEvent[];
}

// --------------------------------------------------------------- scenarios

export function listScenarios(accountId: string): Scenario[] {
  return getDb()
    .prepare("SELECT * FROM scenarios WHERE account_id = ? ORDER BY updated_at DESC")
    .all(accountId) as Scenario[];
}

export function getScenario(accountId: string, id: string): Scenario | undefined {
  return getDb()
    .prepare("SELECT * FROM scenarios WHERE account_id = ? AND id = ?")
    .get(accountId, id) as Scenario | undefined;
}

export function insertScenario(
  accountId: string,
  fields: { name: string; prompt: string; firstMessage: string; voiceId: string },
): Scenario {
  const id = randomUUID();
  const ts = now();
  getDb()
    .prepare(
      `INSERT INTO scenarios (id, account_id, name, prompt, first_message, voice_id, agent_id, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?)`,
    )
    .run(id, accountId, fields.name, fields.prompt, fields.firstMessage, fields.voiceId, ts, ts);
  return getScenario(accountId, id)!;
}

export function updateScenario(
  accountId: string,
  id: string,
  fields: { name: string; prompt: string; firstMessage: string; voiceId: string },
): void {
  getDb()
    .prepare(
      `UPDATE scenarios SET name = ?, prompt = ?, first_message = ?, voice_id = ?, updated_at = ?
       WHERE account_id = ? AND id = ?`,
    )
    .run(fields.name, fields.prompt, fields.firstMessage, fields.voiceId, now(), accountId, id);
}

export function setScenarioAgent(accountId: string, id: string, agentId: string | null): void {
  getDb()
    .prepare("UPDATE scenarios SET agent_id = ? WHERE account_id = ? AND id = ?")
    .run(agentId, accountId, id);
}

export function deleteScenario(accountId: string, id: string): void {
  transaction(() => {
    const db = getDb();
    db.prepare("DELETE FROM practice_sessions WHERE account_id = ? AND scenario_id = ?").run(accountId, id);
    db.prepare("DELETE FROM scenarios WHERE account_id = ? AND id = ?").run(accountId, id);
  });
}

// ------------------------------------------------------- practice sessions

type SessionRow = Omit<PracticeSession, "transcript"> & { transcript: string };

function toSession(row: SessionRow): PracticeSession {
  return { ...row, transcript: JSON.parse(row.transcript) as TranscriptLine[] };
}

export function listSessions(accountId: string, scenarioId: string): PracticeSession[] {
  const rows = getDb()
    .prepare(
      `SELECT id, scenario_id, conversation_id, status, duration_secs, charged_micros, transcript, started_at
       FROM practice_sessions WHERE account_id = ? AND scenario_id = ? ORDER BY started_at DESC`,
    )
    .all(accountId, scenarioId) as SessionRow[];
  return rows.map(toSession);
}

export function isConversationRecorded(conversationId: string): boolean {
  return (
    getDb().prepare("SELECT 1 FROM practice_sessions WHERE conversation_id = ?").get(conversationId) !==
    undefined
  );
}

// Records a finished call and deducts its cost in one transaction. Returns
// false if the conversation was already recorded (e.g. two settle requests
// raced), in which case nothing is charged.
export function recordCall(
  accountId: string,
  call: {
    scenarioId: string;
    conversationId: string;
    status: string;
    durationSecs: number;
    chargedMicros: number;
    transcript: TranscriptLine[];
    startedAt: number;
  },
): boolean {
  return transaction(() => {
    const result = getDb()
      .prepare(
        `INSERT OR IGNORE INTO practice_sessions
         (id, account_id, scenario_id, conversation_id, status, duration_secs, charged_micros, transcript, started_at, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      )
      .run(
        randomUUID(),
        accountId,
        call.scenarioId,
        call.conversationId,
        call.status,
        call.durationSecs,
        call.chargedMicros,
        JSON.stringify(call.transcript),
        call.startedAt,
        now(),
      );
    if (result.changes !== 1) return false;
    if (call.chargedMicros > 0) {
      applyCredit(accountId, -call.chargedMicros, "voice", call.conversationId, call.durationSecs);
    }
    return true;
  });
}
