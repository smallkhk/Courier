// Customers pick from this fixed list. There is deliberately no voice cloning:
// adding a cloned voice needs a consent check from the person whose voice it
// is, which this app does not have yet.
export type Voice = { id: string; name: string; description: string };

const DEFAULT_VOICES: Voice[] = [
  { id: "21m00Tcm4TlvDq8ikWAM", name: "Rachel", description: "Calm, female, American" },
  { id: "EXAVITQu4vr4xnAE8fKL", name: "Sarah", description: "Soft, female, American" },
  { id: "pNInz6obpgDQGcFmaJgB", name: "Adam", description: "Deep, male, American" },
  { id: "TxGEqnHWrfWFTfGW9XjX", name: "Josh", description: "Young, male, American" },
  { id: "ErXwobaYiN019PkySvjV", name: "Antoni", description: "Warm, male, American" },
  { id: "AZnzlk1XvdvUeBnXmlld", name: "Domi", description: "Strong, female, American" },
];

function loadVoices(): Voice[] {
  const raw = process.env.VOICES_JSON;
  if (!raw) return DEFAULT_VOICES;
  const parsed = JSON.parse(raw) as Voice[];
  if (!Array.isArray(parsed) || parsed.length === 0 || parsed.some((v) => !v.id || !v.name)) {
    throw new Error("VOICES_JSON must be a non-empty array of {id, name, description}");
  }
  return parsed;
}

export const VOICES = loadVoices();

export function isAllowedVoice(id: string): boolean {
  return VOICES.some((voice) => voice.id === id);
}
