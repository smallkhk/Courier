import { randomInt } from "node:crypto";
import { kvGet, kvSet, now } from "./db.ts";

// Before a voice is cloned the customer reads a consent statement with four
// random code words, recorded live in the browser. The recording is
// transcribed and must contain the code words, which makes it hard to clone
// someone else's voice from an existing recording.
const WORDS = [
  "river", "orange", "candle", "pepper", "window", "tiger", "garden", "silver", "rocket", "pillow",
  "marble", "bucket", "jungle", "violet", "harbor", "copper", "falcon", "lemon", "meadow", "pocket",
  "tunnel", "velvet", "wagon", "zebra", "anchor", "basket", "cactus", "dolphin", "engine", "forest",
];

const TTL_SECONDS = 30 * 60;

export function issueConsentScript(accountId: string, fullName: string, platformName: string): string {
  const words: string[] = [];
  while (words.length < 4) {
    const word = WORDS[randomInt(WORDS.length)];
    if (!words.includes(word)) words.push(word);
  }
  kvSet(`clone_words:${accountId}`, JSON.stringify({ words, expires: now() + TTL_SECONDS }));
  return `My name is ${fullName}. I'm recording my own voice for ${platformName}, and I agree to let it speak for me on my calls. My code words are ${words.join(", ")}.`;
}

export function consentWords(accountId: string): string[] | null {
  const raw = kvGet(`clone_words:${accountId}`);
  if (!raw) return null;
  const { words, expires } = JSON.parse(raw) as { words: string[]; expires: number };
  return expires >= now() ? words : null;
}

export function clearConsent(accountId: string): void {
  kvSet(`clone_words:${accountId}`, JSON.stringify({ words: [], expires: 0 }));
}

// At least three of the four code words must appear in the transcript.
export function transcriptHasWords(transcript: string, words: string[]): boolean {
  const spoken = new Set(transcript.toLowerCase().match(/[a-z]+/g) ?? []);
  return words.filter((w) => spoken.has(w)).length >= 3;
}
