import { HttpError, requireUser } from "@/lib/auth.ts";
import { deleteVoice } from "@/lib/elevenlabs.ts";
import { json, route } from "@/lib/http.ts";
import { deleteSavedVoice, getSavedVoice } from "@/lib/repo.ts";

type Ctx = { params: Promise<{ id: string }> };

export const DELETE = route(async (request: Request, { params }: Ctx) => {
  const user = await requireUser(request);
  const { id } = await params;
  const voice = getSavedVoice(user.accountId, id);
  if (!voice) throw new HttpError(404, "Voice not found");
  // Clones belong to this account only, so delete them from ElevenLabs too.
  // Library voices may be saved by other accounts as well, so leave them.
  if (voice.source === "clone") await deleteVoice(voice.voice_id);
  deleteSavedVoice(user.accountId, id);
  return json({ ok: true });
});
