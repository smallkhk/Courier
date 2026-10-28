import { listSavedVoices } from "./repo.ts";

// ElevenLabs premade voices every account can use. Customers can add more
// from the voice library, or clone their own voice, on the Voices page.
export type Voice = { id: string; name: string; description: string; previewUrl?: string; source: string };

export const DEFAULT_VOICES: Voice[] = [
  { id: "21m00Tcm4TlvDq8ikWAM", name: "Rachel", description: "Calm, female, American", source: "default" },
  { id: "EXAVITQu4vr4xnAE8fKL", name: "Sarah", description: "Soft, female, American", source: "default" },
  { id: "AZnzlk1XvdvUeBnXmlld", name: "Domi", description: "Strong, female, American", source: "default" },
  { id: "pNInz6obpgDQGcFmaJgB", name: "Adam", description: "Deep, male, American", source: "default" },
  { id: "TxGEqnHWrfWFTfGW9XjX", name: "Josh", description: "Young, male, American", source: "default" },
  { id: "ErXwobaYiN019PkySvjV", name: "Antoni", description: "Warm, male, American", source: "default" },
];

// Every voice this account may put on a call: the defaults plus its own
// saved and cloned voices. Nobody can use another account's cloned voice.
export function voicesFor(accountId: string): Voice[] {
  const saved = listSavedVoices(accountId).map((v) => ({
    id: v.voice_id,
    name: v.name,
    description: v.source === "clone" ? "Your cloned voice" : v.description,
    previewUrl: v.preview_url || undefined,
    source: v.source,
  }));
  return [...saved, ...DEFAULT_VOICES];
}

export function isVoiceAllowed(accountId: string, voiceId: string): boolean {
  return voicesFor(accountId).some((v) => v.id === voiceId);
}
