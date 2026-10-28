import { mkdirSync } from "node:fs";
import { dirname } from "node:path";
import { DatabaseSync } from "node:sqlite";
import { config } from "./config.ts";

const SCHEMA = `
CREATE TABLE IF NOT EXISTS accounts (
  id TEXT PRIMARY KEY,
  credit_micros INTEGER NOT NULL DEFAULT 0,
  agent_id TEXT,
  created_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS users (
  id TEXT PRIMARY KEY,
  account_id TEXT NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  created_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS sessions (
  token_hash TEXT PRIMARY KEY,
  user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  expires_at INTEGER NOT NULL
);

-- Who the AI speaks as: the account owner.
CREATE TABLE IF NOT EXISTS profiles (
  account_id TEXT PRIMARY KEY REFERENCES accounts(id) ON DELETE CASCADE,
  full_name TEXT NOT NULL DEFAULT '',
  nickname TEXT NOT NULL DEFAULT '',
  location TEXT NOT NULL DEFAULT '',
  about TEXT NOT NULL DEFAULT '',
  speaking_style TEXT NOT NULL DEFAULT '',
  updated_at INTEGER NOT NULL
);

-- People the owner calls.
CREATE TABLE IF NOT EXISTS callers (
  id TEXT PRIMARY KEY,
  account_id TEXT NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  relationship TEXT NOT NULL DEFAULT '',
  about TEXT NOT NULL DEFAULT '',
  how_we_talk TEXT NOT NULL DEFAULT '',
  location TEXT NOT NULL DEFAULT '',
  timezone TEXT NOT NULL DEFAULT 'Africa/Lagos',
  language TEXT NOT NULL DEFAULT 'en',
  voice_id TEXT NOT NULL,
  opening TEXT NOT NULL DEFAULT 'wait',
  custom_opening TEXT NOT NULL DEFAULT '',
  created_at INTEGER NOT NULL,
  updated_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS callers_account ON callers(account_id);

-- One row per started call. conversation_id is filled in when ElevenLabs
-- reports the finished conversation; its uniqueness stops double billing.
CREATE TABLE IF NOT EXISTS calls (
  id TEXT PRIMARY KEY,
  account_id TEXT NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  caller_id TEXT,
  topics TEXT NOT NULL DEFAULT '',
  status TEXT NOT NULL,
  conversation_id TEXT UNIQUE,
  duration_secs INTEGER NOT NULL DEFAULT 0,
  charged_micros INTEGER NOT NULL DEFAULT 0,
  transcript TEXT NOT NULL DEFAULT '[]',
  summary TEXT NOT NULL DEFAULT '',
  created_at INTEGER NOT NULL,
  ended_at INTEGER
);
CREATE INDEX IF NOT EXISTS calls_account ON calls(account_id, created_at);
CREATE INDEX IF NOT EXISTS calls_caller ON calls(account_id, caller_id, created_at);

-- Voices an account has saved from the ElevenLabs library or cloned.
CREATE TABLE IF NOT EXISTS voices (
  id TEXT PRIMARY KEY,
  account_id TEXT NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  voice_id TEXT NOT NULL,
  name TEXT NOT NULL,
  description TEXT NOT NULL DEFAULT '',
  preview_url TEXT NOT NULL DEFAULT '',
  source TEXT NOT NULL,
  created_at INTEGER NOT NULL,
  UNIQUE (account_id, voice_id)
);

-- A request to top up: the customer must send exactly amount_micros USDT on
-- network to our address before expires_at.
CREATE TABLE IF NOT EXISTS deposits (
  id TEXT PRIMARY KEY,
  account_id TEXT NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  network TEXT NOT NULL,
  amount_micros INTEGER NOT NULL,
  status TEXT NOT NULL,
  tx_hash TEXT,
  note TEXT NOT NULL DEFAULT '',
  created_at INTEGER NOT NULL,
  expires_at INTEGER NOT NULL,
  credited_at INTEGER
);
CREATE INDEX IF NOT EXISTS deposits_pending ON deposits(network, status, amount_micros);
CREATE INDEX IF NOT EXISTS deposits_account ON deposits(account_id, created_at);

-- Every incoming USDT transfer we have used. A hash can be credited once.
CREATE TABLE IF NOT EXISTS chain_transfers (
  tx_hash TEXT PRIMARY KEY,
  network TEXT NOT NULL,
  deposit_id TEXT,
  amount_micros INTEGER NOT NULL,
  from_address TEXT NOT NULL,
  seen_at INTEGER NOT NULL
);

-- Ledger of every credit change.
CREATE TABLE IF NOT EXISTS usage_events (
  id TEXT PRIMARY KEY,
  account_id TEXT NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  kind TEXT NOT NULL,
  detail TEXT NOT NULL,
  quantity INTEGER NOT NULL,
  amount_micros INTEGER NOT NULL,
  created_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS usage_events_account ON usage_events(account_id, created_at);

CREATE TABLE IF NOT EXISTS kv (
  key TEXT PRIMARY KEY,
  value TEXT NOT NULL
);
`;

let db: DatabaseSync | undefined;

export function getDb(): DatabaseSync {
  if (!db) {
    if (config.databasePath !== ":memory:") {
      mkdirSync(dirname(config.databasePath), { recursive: true });
    }
    db = new DatabaseSync(config.databasePath);
    db.exec("PRAGMA journal_mode = WAL; PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000;");
    db.exec(SCHEMA);
  }
  return db;
}

let depth = 0;

// Runs fn in a transaction. Nested calls join the outer transaction.
export function transaction<T>(fn: () => T): T {
  const conn = getDb();
  if (depth > 0) return fn();
  conn.exec("BEGIN IMMEDIATE");
  depth++;
  try {
    const result = fn();
    conn.exec("COMMIT");
    return result;
  } catch (err) {
    conn.exec("ROLLBACK");
    throw err;
  } finally {
    depth--;
  }
}

export function now(): number {
  return Math.floor(Date.now() / 1000);
}

export function kvGet(key: string): string | undefined {
  const row = getDb().prepare("SELECT value FROM kv WHERE key = ?").get(key) as { value: string } | undefined;
  return row?.value;
}

export function kvSet(key: string, value: string): void {
  getDb()
    .prepare("INSERT INTO kv (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value")
    .run(key, value);
}
