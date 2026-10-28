import { HttpError, requireUser } from "@/lib/auth.ts";
import { clearConsent, consentWords, transcriptHasWords } from "@/lib/clone-consent.ts";
import { config } from "@/lib/config.ts";
import { cloneVoice, transcribe } from "@/lib/elevenlabs.ts";
import { json, route } from "@/lib/http.ts";
import { countClones, getProfile, insertSavedVoice } from "@/lib/repo.ts";

const MIN_BYTES = 100_000;
const MAX_BYTES = 15_000_000;

// Clones the account owner's own voice from a live recording that includes
// the consent statement.
export const POST = route(async (request: Request) => {
  const user = await requireUser(request);
  if (countClones(user.accountId) >= config.maxClonesPerAccount) {
    throw new HttpError(409, "You've reached your cloned voice limit.");
  }
  const words = consentWords(user.accountId);
  if (!words || words.length === 0) throw new HttpError(409, "Your consent script expired. Start the recording again.");

  const form = await request.formData();
  const audio = form.get("audio");
  if (form.get("consent") !== "yes") throw new HttpError(400, "Confirm that this is your own voice.");
  if (!(audio instanceof Blob) || audio.size < MIN_BYTES) {
    throw new HttpError(400, "The recording is too short. Record at least a minute.");
  }
  if (audio.size > MAX_BYTES) throw new HttpError(400, "The recording is too large. Keep it under 3 minutes.");
  const filename = audio.type.includes("mp4") ? "sample.mp4" : "sample.webm";

  const text = await transcribe(audio, filename);
  if (!transcriptHasWords(text, words)) {
    throw new HttpError(
      422,
      "We couldn't hear the consent statement with your code words. Read it clearly at the start of the recording and try again.",
    );
  }

  const name = getProfile(user.accountId).full_name.trim() || "My voice";
  const voiceId = await cloneVoice(`${name} (${user.accountId.slice(0, 8)})`, "Customer's own voice, consent recorded", audio, filename);
  insertSavedVoice(user.accountId, {
    voiceId,
    name: `${name} (my voice)`,
    description: "Your cloned voice",
    previewUrl: "",
    source: "clone",
  });
  clearConsent(user.accountId);
  return json({ ok: true, voiceId });
});
