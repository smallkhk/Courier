import { randomInt } from "node:crypto";
import { kvGet, kvSet, now } from "./db.ts";

// Before a voice is cloned the customer reads a consent statement with four
// random code words, recorded live in the browser. The recording is
// transcribed and must contain the code words, which makes it hard to clone
// someone else's voice from an existing recording.
// Everyday words that speech-to-text gets right in most accents, with one
// spelling in US and UK English.
const WORDS = [
  "river", "orange", "candle", "pepper", "window", "tiger", "garden", "silver", "rocket", "pillow",
  "bucket", "jungle", "copper", "lemon", "pocket", "banana", "doctor", "basket", "cactus", "dolphin",
  "engine", "forest", "monkey", "tomato", "pencil", "camera", "rabbit", "kitchen", "button", "sugar",
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

function editDistance(a: string, b: string): number {
  const row = Array.from({ length: b.length + 1 }, (_, i) => i);
  for (let i = 1; i <= a.length; i++) {
    let prev = row[0];
    row[0] = i;
    for (let j = 1; j <= b.length; j++) {
      const saved = row[j];
      row[j] = Math.min(row[j] + 1, row[j - 1] + 1, prev + (a[i - 1] === b[j - 1] ? 0 : 1));
      prev = saved;
    }
  }
  return row[b.length];
}

// A code word counts as heard when a transcribed word is the same or very
// close (accents and transcription slips: "pockets", "harbour", "candel").
function heard(word: string, spoken: string[]): boolean {
  return spoken.some((s) => s === word || (s.length >= 4 && editDistance(s, word) <= (word.length >= 6 ? 2 : 1)));
}

// At least three of the four code words must appear in the transcript.
export function transcriptHasWords(transcript: string, words: string[]): boolean {
  return matchedWords(transcript, words).length >= 3;
}

export function matchedWords(transcript: string, words: string[]): string[] {
  const spoken = transcript.toLowerCase().match(/[a-z]+/g) ?? [];
  return words.filter((w) => heard(w, spoken));
}
