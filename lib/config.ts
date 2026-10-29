import { getBool, getNumber, getSetting } from "./settings.ts";

// Money is stored as integer micro-dollars (1 USD = 1 USDT = 1_000_000) so
// per-second charges and 6-decimal USDT amounts never lose precision.
export const MICROS_PER_USD = 1_000_000;

const usd = (key: string) => Math.round(getNumber(key) * MICROS_PER_USD);

// Live view of the site settings. Values come from the admin settings page,
// falling back to environment variables, so read them at the time of use.
export const config = {
  get platformName() {
    return getSetting("platformName");
  },
  get announcement() {
    return getSetting("announcement");
  },
  get signupsOpen() {
    return getBool("signupsOpen");
  },
  get callsEnabled() {
    return getBool("callsEnabled");
  },
  get topupsEnabled() {
    return getBool("topupsEnabled");
  },
  get pricePerMinuteMicros() {
    return Math.max(1, usd("pricePerMinuteUsd"));
  },
  get costPerMinuteMicros() {
    return usd("costPerMinuteUsd");
  },
  get signupCreditMicros() {
    return usd("signupCreditUsd");
  },
  get maxCallSeconds() {
    return Math.max(60, Math.round(getNumber("maxCallSeconds")));
  },
  // The AI that does the talking, run by ElevenLabs and billed on the
  // ElevenLabs invoice. Fast and cheap, which matters on a live call.
  get agentLlm() {
    return getSetting("agentLlm");
  },
  get ttsModel() {
    return getSetting("ttsModel");
  },
  get maxClonesPerAccount() {
    return Math.max(0, Math.round(getNumber("maxClonesPerAccount")));
  },
  get minDepositUsd() {
    return Math.max(1, Math.round(getNumber("minDepositUsd")));
  },
  get cloneCodeWordsRequired() {
    return getBool("cloneCodeWordsRequired");
  },
  get changerEnabled() {
    return getBool("changerEnabled");
  },
  get changerFastPerMinuteMicros() {
    return Math.max(1, usd("changerFastPricePerMinuteUsd"));
  },
  get changerNaturalPerMinuteMicros() {
    return Math.max(1, usd("changerNaturalPricePerMinuteUsd"));
  },
  depositWindowSeconds: 60 * 60,
  get wallets() {
    return {
      trc20: getSetting("usdtTrc20Address"),
      bep20: getSetting("usdtBep20Address").toLowerCase(),
    };
  },
};

export function formatUsd(micros: number): string {
  const sign = micros < 0 ? "-" : "";
  return `${sign}$${(Math.abs(micros) / MICROS_PER_USD).toFixed(2)}`;
}

export function minutesFor(micros: number): number {
  return Math.max(0, Math.floor(micros / config.pricePerMinuteMicros));
}
