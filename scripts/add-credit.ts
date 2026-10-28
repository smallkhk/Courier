// Adds credit to the account that owns an email address, until payments are
// wired up. Usage: npm run add-credit -- someone@example.com 10
import { MICROS_PER_USD } from "../lib/config.ts";
import { getDb } from "../lib/db.ts";
import { applyCredit, getCredit } from "../lib/repo.ts";

const [email, usd] = process.argv.slice(2);
const amount = Number(usd);
if (!email || !Number.isFinite(amount) || amount === 0) {
  console.error("Usage: npm run add-credit -- <email> <usd>");
  process.exit(1);
}
const user = getDb().prepare("SELECT account_id FROM users WHERE email = ?").get(email.toLowerCase()) as
  | { account_id: string }
  | undefined;
if (!user) {
  console.error(`No user with email ${email}`);
  process.exit(1);
}
applyCredit(user.account_id, Math.round(amount * MICROS_PER_USD), "credit", "Manual top-up", 0);
console.log(`Credit is now $${(getCredit(user.account_id) / MICROS_PER_USD).toFixed(2)}`);
