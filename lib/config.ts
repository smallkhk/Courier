function num(name: string, fallback: number): number {
  const raw = process.env[name];
  if (raw === undefined || raw === "") return fallback;
  const value = Number(raw);
  if (!Number.isFinite(value) || value < 0) {
    throw new Error(`${name} must be a non-negative number`);
  }
  return value;
}

// Money is stored as integer micro-dollars (1 USD = 1_000_000) so that
// per-second and per-token charges never lose precision.
export const MICROS_PER_USD = 1_000_000;

export const config = {
  platformName: process.env.PLATFORM_NAME || "Practice Voice",
  databasePath: process.env.DATABASE_PATH || "./data/app.db",
  pricePerMinuteMicros: Math.round(num("PRICE_PER_MINUTE_USD", 0.05) * MICROS_PER_USD),
  llmMarkup: num("LLM_MARKUP", 1.5),
  signupCreditMicros: Math.round(num("SIGNUP_CREDIT_USD", 1) * MICROS_PER_USD),
  maxCallSeconds: Math.max(60, Math.round(num("MAX_CALL_SECONDS", 900))),
  claudeModel: "claude-opus-5-5",
  // Claude Opus 5.5 list price, micro-dollars per token ($4 / $20 per million).
  claudeInputMicrosPerToken: 4,
  claudeOutputMicrosPerToken: 20,
};

export function formatUsd(micros: number): string {
  const sign = micros < 0 ? "-" : "";
  return `${sign}$${(Math.abs(micros) / MICROS_PER_USD).toFixed(2)}`;
}
