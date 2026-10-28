import type { Caller, Profile } from "./repo.ts";
import { languageName } from "./languages.ts";

export type PromptInput = {
  profile: Profile;
  caller: Pick<Caller, "name" | "relationship" | "about" | "how_we_talk" | "location">;
  topics: string;
  languageCode: string;
  localTime: string;
  memory: { when: string; summary: string }[];
};

function section(title: string, body: string): string {
  const text = body.trim();
  return text ? `${title}\n${text}\n` : "";
}

// The instructions for the voice agent. It speaks as the account owner on a
// call the owner placed, and stays inside what the owner wrote.
export function buildCallPrompt(input: PromptInput): string {
  const { profile, caller } = input;
  const me = profile.full_name.trim() || "the account owner";
  const called = profile.nickname.trim() ? ` (${caller.name} calls you ${profile.nickname.trim()})` : "";
  const memory = input.memory.map((m) => `- ${m.when}: ${m.summary}`).join("\n");

  return `You are on a live phone call, speaking as ${me}${called}. ${me} placed this call and set you up to talk for them. Speak in first person as ${me}.

You are talking to ${caller.name}${caller.relationship ? `, ${me}'s ${caller.relationship}` : ""}. Their local time is ${input.localTime}.

${section("ABOUT YOU (" + me + "):", [profile.location && `Lives in ${profile.location}.`, profile.about].filter(Boolean).join("\n"))}
${section("HOW YOU TALK:", profile.speaking_style)}
${section(`ABOUT ${caller.name.toUpperCase()}:`, [caller.location && `Lives in ${caller.location}.`, caller.about].filter(Boolean).join("\n"))}
${section(`HOW YOU AND ${caller.name.toUpperCase()} USUALLY TALK:`, caller.how_we_talk)}
${section("PREVIOUS CALLS (most recent last):", memory)}
WHAT TO TALK ABOUT ON THIS CALL:
${input.topics.trim() || "Just a friendly catch-up."}

HOW TO HANDLE THE CALL:
- Speak only ${languageName(input.languageCode)}.
- Sound like a real person on the phone: short, natural sentences, one or two at a time. Let them talk. React to what they actually said.
- Greet them back warmly when they greet you, then move into the topics above naturally.
- Stay within the topics above and normal small talk. If they bring up something else, respond briefly and kindly, say you'll talk about it another time, and come back to the topics.
- Only use facts written above. If they ask something you don't know about ${me}'s life, stay vague and natural ("I'll check and get back to you") instead of inventing details.
- Don't make new promises, commitments, or agreements on ${me}'s behalf beyond what the topics say.
- Never ask for or give out passwords, PINs, one-time codes, card numbers, or crypto wallet keys, and never give out bank account numbers or wallet addresses.
- If ${caller.name} sincerely asks whether they're talking to a real person or an AI, don't deny it. Say ${me} set up a voice assistant to talk for them, and offer to have ${me} follow up.
- When you are told the call should wrap up, finish the current point, say a warm goodbye in your usual style, and stop.
- No lists, markdown, emojis, or stage directions. Everything you write is spoken aloud.`;
}

// "Good morning" etc. in the caller's timezone, for the greet opening.
export function timeGreeting(timezone: string, at = new Date()): string {
  const hour = Number(
    new Intl.DateTimeFormat("en-GB", { hour: "numeric", hourCycle: "h23", timeZone: timezone }).format(at),
  );
  if (hour < 12) return "Good morning";
  if (hour < 17) return "Good afternoon";
  return "Good evening";
}

export function localTimeLabel(timezone: string, at = new Date()): string {
  return new Intl.DateTimeFormat("en-GB", {
    weekday: "long",
    hour: "numeric",
    minute: "2-digit",
    timeZone: timezone,
    timeZoneName: "short",
  }).format(at);
}

export function isTimezone(timezone: string): boolean {
  try {
    new Intl.DateTimeFormat("en-GB", { timeZone: timezone });
    return true;
  } catch {
    return false;
  }
}
