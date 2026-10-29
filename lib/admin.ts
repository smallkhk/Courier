// Admin-only data access. Unlike lib/repo.ts these queries deliberately read
// across every account, so only admin pages and /api/admin routes may import
// this file, and every change is written to the admin log.
import { randomUUID } from "node:crypto";
import { hashPassword } from "./passwords.ts";
import { config, MICROS_PER_USD } from "./config.ts";
import { getDb, now, transaction } from "./db.ts";
import { deleteAgent, deleteVoice } from "./elevenlabs.ts";
import { HttpError } from "./errors.ts";
import { applyCredit, type Call, type Deposit, type TranscriptLine } from "./repo.ts";

function rows<T>(sql: string, ...args: (string | number | null)[]): T[] {
  return (getDb().prepare(sql).all(...args) as object[]).map((r) => ({ ...r }) as T);
}

function row<T>(sql: string, ...args: (string | number | null)[]): T | undefined {
  const r = getDb().prepare(sql).get(...args) as object | undefined;
  return r ? ({ ...r } as T) : undefined;
}

// ------------------------------------------------------------------- audit

export type AdminLogEntry = { id: string; admin_email: string; action: string; target: string; detail: string; created_at: number };

export function logAdmin(adminEmail: string, action: string, target: string, detail: string): void {
  getDb()
    .prepare("INSERT INTO admin_log (id, admin_email, action, target, detail, created_at) VALUES (?, ?, ?, ?, ?, ?)")
    .run(randomUUID(), adminEmail, action, target, detail, now());
}

export function listAdminLog(limit = 200): AdminLogEntry[] {
  return rows<AdminLogEntry>("SELECT * FROM admin_log ORDER BY created_at DESC, rowid DESC LIMIT ?", limit);
}

// ------------------------------------------------------------------- stats

export type Stats = {
  users: number;
  newUsers7d: number;
  blocked: number;
  depositedMicros: number;
  deposited7dMicros: number;
  callRevenueMicros: number;
  callSeconds: number;
  calls: number;
  calls7d: number;
  estCostMicros: number;
  balancesMicros: number;
  openTopups: number;
  reviewTopups: number;
  clones: number;
  changerRevenueMicros: number;
  changerSeconds: number;
  daily: { day: string; revenue: number; deposits: number; calls: number }[];
};

export function getStats(): Stats {
  const weekAgo = now() - 7 * 86400;
  const n = (sql: string, ...args: (string | number)[]) =>
    (getDb().prepare(sql).get(...args) as { n: number | null }).n ?? 0;
  const callSeconds = n("SELECT SUM(duration_secs) AS n FROM calls WHERE conversation_id IS NOT NULL");
  const since = now() - 14 * 86400;
  const dayKey = "strftime('%Y-%m-%d', created_at, 'unixepoch')";
  const revenueByDay = rows<{ day: string; v: number }>(
    `SELECT ${dayKey} AS day, SUM(charged_micros) AS v FROM calls WHERE conversation_id IS NOT NULL AND created_at >= ? GROUP BY day`,
    since,
  );
  const depositsByDay = rows<{ day: string; v: number }>(
    `SELECT ${dayKey} AS day, SUM(amount_micros) AS v FROM usage_events WHERE kind = 'deposit' AND created_at >= ? GROUP BY day`,
    since,
  );
  const callsByDay = rows<{ day: string; v: number }>(
    `SELECT ${dayKey} AS day, COUNT(*) AS v FROM calls WHERE conversation_id IS NOT NULL AND created_at >= ? GROUP BY day`,
    since,
  );
  const daily: Stats["daily"] = [];
  for (let i = 13; i >= 0; i--) {
    const day = new Date((now() - i * 86400) * 1000).toISOString().slice(0, 10);
    daily.push({
      day,
      revenue: revenueByDay.find((r) => r.day === day)?.v ?? 0,
      deposits: depositsByDay.find((r) => r.day === day)?.v ?? 0,
      calls: callsByDay.find((r) => r.day === day)?.v ?? 0,
    });
  }
  return {
    users: n("SELECT COUNT(*) AS n FROM users"),
    newUsers7d: n("SELECT COUNT(*) AS n FROM users WHERE created_at >= ?", weekAgo),
    blocked: n("SELECT COUNT(*) AS n FROM accounts WHERE blocked_at IS NOT NULL"),
    depositedMicros: n("SELECT SUM(amount_micros) AS n FROM usage_events WHERE kind = 'deposit'"),
    deposited7dMicros: n("SELECT SUM(amount_micros) AS n FROM usage_events WHERE kind = 'deposit' AND created_at >= ?", weekAgo),
    callRevenueMicros: n("SELECT SUM(charged_micros) AS n FROM calls WHERE conversation_id IS NOT NULL"),
    callSeconds,
    calls: n("SELECT COUNT(*) AS n FROM calls WHERE conversation_id IS NOT NULL"),
    calls7d: n("SELECT COUNT(*) AS n FROM calls WHERE conversation_id IS NOT NULL AND created_at >= ?", weekAgo),
    estCostMicros: Math.round((callSeconds * config.costPerMinuteMicros) / 60),
    balancesMicros: n("SELECT SUM(credit_micros) AS n FROM accounts"),
    openTopups: n("SELECT COUNT(*) AS n FROM deposits WHERE status = 'pending'"),
    reviewTopups: n("SELECT COUNT(*) AS n FROM deposits WHERE status = 'review'"),
    clones: n("SELECT COUNT(*) AS n FROM voices WHERE source = 'clone'"),
    changerRevenueMicros: n("SELECT SUM(charged_micros) AS n FROM changer_sessions"),
    changerSeconds: Math.round(n("SELECT SUM(billed_ms) AS n FROM changer_sessions") / 1000),
    daily,
  };
}

// ------------------------------------------------------------------- users

export type AdminUserRow = {
  user_id: string;
  account_id: string;
  email: string;
  full_name: string | null;
  is_admin: number;
  credit_micros: number;
  blocked_at: number | null;
  block_reason: string;
  created_at: number;
  calls: number;
  deposited_micros: number;
};

const USER_SELECT = `
  SELECT u.id AS user_id, u.account_id, u.email, p.full_name, u.is_admin, a.credit_micros, a.blocked_at,
    a.block_reason, u.created_at,
    (SELECT COUNT(*) FROM calls c WHERE c.account_id = a.id AND c.conversation_id IS NOT NULL) AS calls,
    (SELECT COALESCE(SUM(amount_micros), 0) FROM usage_events e WHERE e.account_id = a.id AND e.kind = 'deposit') AS deposited_micros
  FROM users u JOIN accounts a ON a.id = u.account_id LEFT JOIN profiles p ON p.account_id = a.id`;

export function listUsers(opts: { search?: string; filter?: string; limit?: number; offset?: number }): {
  users: AdminUserRow[];
  total: number;
} {
  const where: string[] = [];
  const args: (string | number)[] = [];
  if (opts.search) {
    where.push("(u.email LIKE ? OR p.full_name LIKE ? OR u.id = ?)");
    args.push(`%${opts.search}%`, `%${opts.search}%`, opts.search);
  }
  if (opts.filter === "blocked") where.push("a.blocked_at IS NOT NULL");
  if (opts.filter === "admins") where.push("u.is_admin = 1");
  if (opts.filter === "paying") where.push("EXISTS (SELECT 1 FROM usage_events e WHERE e.account_id = a.id AND e.kind = 'deposit')");
  const clause = where.length ? `WHERE ${where.join(" AND ")}` : "";
  const total = (
    getDb()
      .prepare(`SELECT COUNT(*) AS n FROM users u JOIN accounts a ON a.id = u.account_id LEFT JOIN profiles p ON p.account_id = a.id ${clause}`)
      .get(...args) as { n: number }
  ).n;
  const users = rows<AdminUserRow>(
    `${USER_SELECT} ${clause} ORDER BY u.created_at DESC LIMIT ? OFFSET ?`,
    ...args,
    opts.limit ?? 50,
    opts.offset ?? 0,
  );
  return { users, total };
}

export function getUser(userId: string): AdminUserRow | undefined {
  return row<AdminUserRow>(`${USER_SELECT} WHERE u.id = ?`, userId);
}

export function getUserExtras(accountId: string) {
  return {
    profile: row<{ full_name: string; nickname: string; location: string; about: string; speaking_style: string }>(
      "SELECT full_name, nickname, location, about, speaking_style FROM profiles WHERE account_id = ?",
      accountId,
    ),
    callers: rows<{ id: string; name: string; relationship: string; location: string; language: string }>(
      "SELECT id, name, relationship, location, language FROM callers WHERE account_id = ? ORDER BY updated_at DESC",
      accountId,
    ),
    usage: rows<{ id: string; kind: string; detail: string; quantity: number; amount_micros: number; created_at: number }>(
      "SELECT id, kind, detail, quantity, amount_micros, created_at FROM usage_events WHERE account_id = ? ORDER BY created_at DESC, rowid DESC LIMIT 100",
      accountId,
    ),
    voices: rows<{ id: string; voice_id: string; name: string; source: string; created_at: number }>(
      "SELECT id, voice_id, name, source, created_at FROM voices WHERE account_id = ? ORDER BY created_at DESC",
      accountId,
    ),
    sessions: (getDb().prepare("SELECT COUNT(*) AS n FROM sessions s JOIN users u ON u.id = s.user_id WHERE u.account_id = ? AND s.expires_at > ?").get(accountId, now()) as { n: number }).n,
  };
}

function requireUserRow(userId: string): AdminUserRow {
  const user = getUser(userId);
  if (!user) throw new HttpError(404, "User not found");
  return user;
}

export function adjustCredit(admin: string, userId: string, amountUsd: number, reason: string): void {
  const user = requireUserRow(userId);
  if (!Number.isFinite(amountUsd) || amountUsd === 0 || Math.abs(amountUsd) > 100_000) {
    throw new HttpError(400, "Enter an amount, e.g. 5 to add or -5 to remove");
  }
  const micros = Math.round(amountUsd * MICROS_PER_USD);
  applyCredit(user.account_id, micros, micros > 0 ? "credit" : "adjustment", reason || "By support", 0);
  logAdmin(admin, micros > 0 ? "credit.add" : "credit.remove", user.email, `${amountUsd} USD · ${reason || "no reason"}`);
}

export function setBlocked(admin: string, adminUserId: string, userId: string, blocked: boolean, reason: string): void {
  const user = requireUserRow(userId);
  if (userId === adminUserId) throw new HttpError(400, "You can't block yourself");
  transaction(() => {
    getDb()
      .prepare("UPDATE accounts SET blocked_at = ?, block_reason = ? WHERE id = ?")
      .run(blocked ? now() : null, blocked ? reason : "", user.account_id);
    if (blocked) getDb().prepare("DELETE FROM sessions WHERE user_id = ?").run(userId);
  });
  logAdmin(admin, blocked ? "user.block" : "user.unblock", user.email, reason);
}

export function setAdmin(admin: string, adminUserId: string, userId: string, makeAdmin: boolean): void {
  const user = requireUserRow(userId);
  if (userId === adminUserId && !makeAdmin) throw new HttpError(400, "You can't remove your own admin access");
  getDb().prepare("UPDATE users SET is_admin = ? WHERE id = ?").run(makeAdmin ? 1 : 0, userId);
  logAdmin(admin, makeAdmin ? "user.make_admin" : "user.remove_admin", user.email, "");
}

export function setPassword(admin: string, userId: string, password: string): void {
  const user = requireUserRow(userId);
  if (password.length < 10) throw new HttpError(400, "Password must be at least 10 characters");
  transaction(() => {
    getDb().prepare("UPDATE users SET password_hash = ? WHERE id = ?").run(hashPassword(password), userId);
    getDb().prepare("DELETE FROM sessions WHERE user_id = ?").run(userId);
  });
  logAdmin(admin, "user.set_password", user.email, "Signed out of all devices");
}

export function signOutEverywhere(admin: string, userId: string): void {
  const user = requireUserRow(userId);
  getDb().prepare("DELETE FROM sessions WHERE user_id = ?").run(userId);
  logAdmin(admin, "user.sign_out", user.email, "");
}

export async function deleteUser(admin: string, adminUserId: string, userId: string): Promise<void> {
  const user = requireUserRow(userId);
  if (userId === adminUserId) throw new HttpError(400, "You can't delete yourself");
  // Remove what this account owns on ElevenLabs first: its cloned voices and its agent.
  for (const v of rows<{ voice_id: string }>("SELECT voice_id FROM voices WHERE account_id = ? AND source = 'clone'", user.account_id)) {
    await deleteVoice(v.voice_id).catch((err) => console.warn("delete clone failed", err));
  }
  const agent = row<{ agent_id: string | null }>("SELECT agent_id FROM accounts WHERE id = ?", user.account_id);
  if (agent?.agent_id) await deleteAgent(agent.agent_id).catch((err) => console.warn("delete agent failed", err));
  getDb().prepare("DELETE FROM accounts WHERE id = ?").run(user.account_id);
  logAdmin(admin, "user.delete", user.email, `Balance was ${(user.credit_micros / MICROS_PER_USD).toFixed(2)} USD`);
}

// ---------------------------------------------------------------- deposits

export type AdminDeposit = Deposit & { email: string; user_id: string };

export function listAllDeposits(status?: string, limit = 200): AdminDeposit[] {
  const clause = status ? "WHERE d.status = ?" : "";
  const args: (string | number)[] = status ? [status, limit] : [limit];
  return rows<AdminDeposit>(
    `SELECT d.id, d.network, d.amount_micros, d.status, d.tx_hash, d.note, d.created_at, d.expires_at, d.credited_at,
       u.email, u.id AS user_id
     FROM deposits d JOIN users u ON u.account_id = d.account_id ${clause}
     ORDER BY CASE d.status WHEN 'review' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END, d.created_at DESC LIMIT ?`,
    ...args,
  );
}

export function listUserDeposits(accountId: string): Deposit[] {
  return rows<Deposit>(
    "SELECT id, network, amount_micros, status, tx_hash, note, created_at, expires_at, credited_at FROM deposits WHERE account_id = ? ORDER BY created_at DESC LIMIT 100",
    accountId,
  );
}

// ------------------------------------------------------------------- calls

export type AdminCall = Call & { email: string; user_id: string; caller_name: string | null };

export function listAllCalls(opts: { accountId?: string; limit?: number }): AdminCall[] {
  const clause = opts.accountId ? "AND c.account_id = ?" : "";
  const args: (string | number)[] = opts.accountId ? [opts.accountId, opts.limit ?? 100] : [opts.limit ?? 100];
  return rows<Omit<AdminCall, "transcript"> & { transcript: string }>(
    `SELECT c.id, c.caller_id, c.topics, c.status, c.conversation_id, c.duration_secs, c.charged_micros, c.transcript,
       c.summary, c.created_at, c.ended_at, u.email, u.id AS user_id, k.name AS caller_name
     FROM calls c JOIN users u ON u.account_id = c.account_id LEFT JOIN callers k ON k.id = c.caller_id
     WHERE c.conversation_id IS NOT NULL ${clause}
     ORDER BY c.created_at DESC LIMIT ?`,
    ...args,
  ).map((c) => ({ ...c, transcript: JSON.parse(c.transcript) as TranscriptLine[] }));
}

// ------------------------------------------------------------------ voices

export type AdminVoice = { id: string; voice_id: string; name: string; source: string; created_at: number; email: string; user_id: string };

export function listAllVoices(): AdminVoice[] {
  return rows<AdminVoice>(
    `SELECT v.id, v.voice_id, v.name, v.source, v.created_at, u.email, u.id AS user_id
     FROM voices v JOIN users u ON u.account_id = v.account_id
     ORDER BY CASE v.source WHEN 'clone' THEN 0 ELSE 1 END, v.created_at DESC`,
  );
}

export async function removeVoice(admin: string, voiceRowId: string): Promise<void> {
  const v = row<AdminVoice>(
    "SELECT v.id, v.voice_id, v.name, v.source, v.created_at, u.email, u.id AS user_id FROM voices v JOIN users u ON u.account_id = v.account_id WHERE v.id = ?",
    voiceRowId,
  );
  if (!v) throw new HttpError(404, "Voice not found");
  if (v.source === "clone") await deleteVoice(v.voice_id);
  getDb().prepare("DELETE FROM voices WHERE id = ?").run(voiceRowId);
  logAdmin(admin, v.source === "clone" ? "voice.delete_clone" : "voice.remove", v.email, v.name);
}
