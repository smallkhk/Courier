// Site settings the admin can change from the admin page. Each one falls
// back to its environment variable (and then a default) until an admin saves
// a value, which is stored in the database and takes effect immediately.
import { kvGet, kvSet } from "./db.ts";

type Kind = "text" | "number" | "integer" | "boolean" | "trc20" | "bep20";

export type SettingDef = {
  key: string;
  label: string;
  help: string;
  kind: Kind;
  env?: string;
  fallback: string;
  group: "General" | "Pricing" | "Calls" | "Voice changer" | "Payments" | "AI and voice";
  min?: number;
};

export const SETTINGS: SettingDef[] = [
  { key: "platformName", label: "Site name", help: "Shown in the header and page titles.", kind: "text", env: "PLATFORM_NAME", fallback: "CallMate", group: "General" },
  { key: "announcement", label: "Announcement banner", help: "Shown to every signed-in user. Leave empty to hide.", kind: "text", fallback: "", group: "General" },
  { key: "signupsOpen", label: "New sign-ups allowed", help: "Turn off to stop new accounts being created.", kind: "boolean", fallback: "true", group: "General" },
  { key: "pricePerMinuteUsd", label: "Price per minute (USD)", help: "What customers pay per minute of call, charged by the second.", kind: "number", env: "PRICE_PER_MINUTE_USD", fallback: "0.2", group: "Pricing", min: 0.01 },
  { key: "costPerMinuteUsd", label: "Your cost per minute (USD)", help: "Roughly what ElevenLabs charges you per minute. Used for the profit estimate only.", kind: "number", fallback: "0.1", group: "Pricing", min: 0 },
  { key: "signupCreditUsd", label: "Free credit for new accounts (USD)", help: "Given once at sign-up.", kind: "number", env: "SIGNUP_CREDIT_USD", fallback: "0", group: "Pricing", min: 0 },
  { key: "callsEnabled", label: "Calls enabled", help: "Turn off to stop anyone starting new calls (maintenance mode).", kind: "boolean", fallback: "true", group: "Calls" },
  { key: "maxCallSeconds", label: "Longest call (seconds)", help: "Hard limit for a single call.", kind: "integer", env: "MAX_CALL_SECONDS", fallback: "1800", group: "Calls", min: 60 },
  { key: "changerEnabled", label: "Voice changer enabled", help: "Turn off to stop anyone using the live voice changer.", kind: "boolean", fallback: "true", group: "Voice changer" },
  { key: "changerFastPricePerMinuteUsd", label: "Fast mode price per minute (USD)", help: "Charged for the time the changer is on, in prepaid 1-minute blocks; unused time is refunded when it stops.", kind: "number", env: "CHANGER_FAST_PRICE_PER_MINUTE_USD", fallback: "0.15", group: "Voice changer", min: 0.01 },
  { key: "changerNaturalPricePerMinuteUsd", label: "Natural mode price per minute (USD)", help: "Charged per second of speech sent for conversion.", kind: "number", env: "CHANGER_NATURAL_PRICE_PER_MINUTE_USD", fallback: "0.2", group: "Voice changer", min: 0.01 },
  { key: "topupsEnabled", label: "Top-ups enabled", help: "Turn off to stop new USDT top-ups.", kind: "boolean", fallback: "true", group: "Payments" },
  { key: "minDepositUsd", label: "Minimum top-up (USDT)", help: "Whole number.", kind: "integer", env: "MIN_DEPOSIT_USD", fallback: "3", group: "Payments", min: 1 },
  { key: "usdtTrc20Address", label: "USDT TRC20 wallet", help: "Your TRON address that receives payments. Empty turns TRC20 off.", kind: "trc20", env: "USDT_TRC20_ADDRESS", fallback: "", group: "Payments" },
  { key: "usdtBep20Address", label: "USDT BEP20 wallet", help: "Your BNB Smart Chain address that receives payments. Empty turns BEP20 off.", kind: "bep20", env: "USDT_BEP20_ADDRESS", fallback: "", group: "Payments" },
  { key: "agentLlm", label: "AI model", help: "The model ElevenLabs runs for the conversation, e.g. claude-haiku-5-5 or claude-sonnet-5-5.", kind: "text", env: "AGENT_LLM", fallback: "claude-haiku-5-5", group: "AI and voice" },
  { key: "ttsModel", label: "Voice model", help: "ElevenLabs voice model, e.g. eleven_flash_v2_5 (fast, multilingual).", kind: "text", env: "TTS_MODEL", fallback: "eleven_flash_v2_5", group: "AI and voice" },
  { key: "cloneCodeWordsRequired", label: "Require spoken code words for voice cloning", help: "When on, people must read the code words aloud before a clone is made. Turn off only for trusted cloning, then back on.", kind: "boolean", fallback: "true", group: "AI and voice" },
  { key: "maxClonesPerAccount", label: "Cloned voices per account", help: "0 turns voice cloning off.", kind: "integer", env: "MAX_CLONES_PER_ACCOUNT", fallback: "1", group: "AI and voice", min: 0 },
];

const BY_KEY = new Map(SETTINGS.map((s) => [s.key, s]));

function def(key: string): SettingDef {
  const found = BY_KEY.get(key);
  if (!found) throw new Error(`Unknown setting ${key}`);
  return found;
}

export function getSetting(key: string): string {
  const d = def(key);
  const stored = kvGet(`setting:${key}`);
  if (stored !== undefined) return stored;
  const fromEnv = d.env ? process.env[d.env] : undefined;
  return fromEnv !== undefined && fromEnv !== "" ? fromEnv : d.fallback;
}

export function getNumber(key: string): number {
  const value = Number(getSetting(key));
  return Number.isFinite(value) ? value : Number(def(key).fallback);
}

export function getBool(key: string): boolean {
  return getSetting(key) === "true";
}

// Checks and normalises a value an admin entered. Throws with a message the
// admin page can show.
export function validateSetting(key: string, raw: string): string {
  const d = def(key);
  const value = raw.trim();
  switch (d.kind) {
    case "text":
      if (value.length > 500) throw new Error(`${d.label}: keep it under 500 characters`);
      if (key !== "announcement" && !value) throw new Error(`${d.label} can't be empty`);
      return value;
    case "boolean":
      if (value !== "true" && value !== "false") throw new Error(`${d.label}: choose on or off`);
      return value;
    case "number":
    case "integer": {
      const n = Number(value);
      if (value === "" || !Number.isFinite(n)) throw new Error(`${d.label} must be a number`);
      if (d.kind === "integer" && !Number.isInteger(n)) throw new Error(`${d.label} must be a whole number`);
      if (d.min !== undefined && n < d.min) throw new Error(`${d.label} must be at least ${d.min}`);
      return String(n);
    }
    case "trc20":
      if (value && !/^T[1-9A-HJ-NP-Za-km-z]{33}$/.test(value)) throw new Error("That isn't a valid TRON address");
      return value;
    case "bep20":
      if (value && !/^0x[0-9a-fA-F]{40}$/.test(value)) throw new Error("That isn't a valid BNB Smart Chain address");
      return value.toLowerCase();
  }
}

export function saveSetting(key: string, raw: string): { before: string; after: string } {
  const before = getSetting(key);
  const after = validateSetting(key, raw);
  kvSet(`setting:${key}`, after);
  return { before, after };
}

export function allSettings(): (SettingDef & { value: string })[] {
  return SETTINGS.map((s) => ({ ...s, value: getSetting(s.key) }));
}
