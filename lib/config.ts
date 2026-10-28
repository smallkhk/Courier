function num(name: string, fallback: number): number {
  const raw = process.env[name];
  if (raw === undefined || raw === "") return fallback;
  const value = Number(raw);
  if (!Number.isFinite(value) || value < 0) {
    throw new Error(`${name} must be a non-negative number`);
  }
  return value;
}

// Money is stored as integer micro-dollars (1 USD = 1 USDT = 1_000_000) so
// per-second charges and 6-decimal USDT amounts never lose precision.
export const MICROS_PER_USD = 1_000_000;

export const config = {
  platformName: process.env.PLATFORM_NAME || "CallMate",
  databasePath: process.env.DATABASE_PATH || "./data/app.db",
  pricePerMinuteMicros: Math.round(num("PRICE_PER_MINUTE_USD", 0.2) * MICROS_PER_USD),
  signupCreditMicros: Math.round(num("SIGNUP_CREDIT_USD", 0) * MICROS_PER_USD),
  maxCallSeconds: Math.max(60, Math.round(num("MAX_CALL_SECONDS", 1800))),
  // The AI that does the talking, run by ElevenLabs and billed on the
  // ElevenLabs invoice. Fast and cheap, which matters on a live call.
  agentLlm: process.env.AGENT_LLM || "claude-haiku-5-5",
  ttsModel: process.env.TTS_MODEL || "eleven_flash_v2_5",
  maxClonesPerAccount: Math.round(num("MAX_CLONES_PER_ACCOUNT", 1)),
  minDepositUsd: Math.max(1, Math.round(num("MIN_DEPOSIT_USD", 3))),
  depositWindowSeconds: 60 * 60,
  wallets: {
    trc20: process.env.USDT_TRC20_ADDRESS || "",
    bep20: (process.env.USDT_BEP20_ADDRESS || "").toLowerCase(),
  },
};

export function formatUsd(micros: number): string {
  const sign = micros < 0 ? "-" : "";
  return `${sign}$${(Math.abs(micros) / MICROS_PER_USD).toFixed(2)}`;
}

export function minutesFor(micros: number): number {
  return Math.max(0, Math.floor(micros / config.pricePerMinuteMicros));
}
