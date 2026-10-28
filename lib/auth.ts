import { createHash, randomBytes, randomUUID, scryptSync, timingSafeEqual } from "node:crypto";
import { cookies, headers } from "next/headers";
import { config } from "./config.ts";
import { getDb, now, transaction } from "./db.ts";
import { applyCredit } from "./repo.ts";

const COOKIE = "session";
const SESSION_SECONDS = 60 * 60 * 24 * 30;

export type CurrentUser = { userId: string; accountId: string; email: string; accountName: string };

export class HttpError extends Error {
  constructor(
    public status: number,
    message: string,
  ) {
    super(message);
  }
}

export function hashPassword(password: string): string {
  const salt = randomBytes(16);
  const hash = scryptSync(password, salt, 64);
  return `scrypt$${salt.toString("hex")}$${hash.toString("hex")}`;
}

export function verifyPassword(password: string, stored: string): boolean {
  const [scheme, saltHex, hashHex] = stored.split("$");
  if (scheme !== "scrypt" || !saltHex || !hashHex) return false;
  const expected = Buffer.from(hashHex, "hex");
  const actual = scryptSync(password, Buffer.from(saltHex, "hex"), expected.length);
  return timingSafeEqual(expected, actual);
}

function hashToken(token: string): string {
  return createHash("sha256").update(token).digest("hex");
}

export function createAccount(email: string, password: string, accountName: string): string {
  const userId = randomUUID();
  transaction(() => {
    const db = getDb();
    const accountId = randomUUID();
    db.prepare("INSERT INTO accounts (id, name, credit_micros, created_at) VALUES (?, ?, 0, ?)").run(
      accountId,
      accountName,
      now(),
    );
    db.prepare(
      "INSERT INTO users (id, account_id, email, password_hash, created_at) VALUES (?, ?, ?, ?, ?)",
    ).run(userId, accountId, email, hashPassword(password), now());
    if (config.signupCreditMicros > 0) {
      applyCredit(accountId, config.signupCreditMicros, "credit", "Signup credit", 0);
    }
  });
  return userId;
}

export function findUserByEmail(email: string): { id: string; password_hash: string } | undefined {
  return getDb().prepare("SELECT id, password_hash FROM users WHERE email = ?").get(email) as
    | { id: string; password_hash: string }
    | undefined;
}

export async function startSession(userId: string): Promise<void> {
  const token = randomBytes(32).toString("base64url");
  const expires = now() + SESSION_SECONDS;
  getDb()
    .prepare("INSERT INTO sessions (token_hash, user_id, expires_at) VALUES (?, ?, ?)")
    .run(hashToken(token), userId, expires);
  (await cookies()).set(COOKIE, token, {
    httpOnly: true,
    sameSite: "lax",
    secure: process.env.NODE_ENV === "production",
    path: "/",
    maxAge: SESSION_SECONDS,
  });
}

export async function endSession(): Promise<void> {
  const jar = await cookies();
  const token = jar.get(COOKIE)?.value;
  if (token) getDb().prepare("DELETE FROM sessions WHERE token_hash = ?").run(hashToken(token));
  jar.delete(COOKIE);
}

export async function currentUser(): Promise<CurrentUser | null> {
  const token = (await cookies()).get(COOKIE)?.value;
  if (!token) return null;
  const row = getDb()
    .prepare(
      `SELECT u.id AS userId, u.account_id AS accountId, u.email AS email, a.name AS accountName, s.expires_at AS expiresAt
       FROM sessions s JOIN users u ON u.id = s.user_id JOIN accounts a ON a.id = u.account_id
       WHERE s.token_hash = ?`,
    )
    .get(hashToken(token)) as (CurrentUser & { expiresAt: number }) | undefined;
  if (!row || row.expiresAt < now()) return null;
  return { userId: row.userId, accountId: row.accountId, email: row.email, accountName: row.accountName };
}

// For API routes: returns the signed-in user or throws 401. On requests that
// change state it also rejects cross-site Origins as a CSRF guard on top of
// the SameSite=Lax cookie.
export async function requireUser(request?: Request): Promise<CurrentUser> {
  if (request && request.method !== "GET" && request.method !== "HEAD") {
    const origin = request.headers.get("origin");
    const host = (await headers()).get("host");
    if (origin && host && new URL(origin).host !== host) {
      throw new HttpError(403, "Cross-site request blocked");
    }
  }
  const user = await currentUser();
  if (!user) throw new HttpError(401, "Please sign in");
  return user;
}
