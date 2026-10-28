import { z } from "zod";
import { requireUser } from "@/lib/auth.ts";
import { addLibraryVoice } from "@/lib/elevenlabs.ts";
import { json, readJson, route } from "@/lib/http.ts";
import { insertSavedVoice } from "@/lib/repo.ts";

const Body = z.object({
  publicOwnerId: z.string().min(1).max(200),
  voiceId: z.string().min(1).max(200),
  name: z.string().trim().min(1).max(100),
  description: z.string().max(300).default(""),
  previewUrl: z.string().url().max(1000).or(z.literal("")).default(""),
});

// Saves a voice from the ElevenLabs library so it can be used on calls.
export const POST = route(async (request: Request) => {
  const user = await requireUser(request);
  const body = await readJson(request, Body);
  const voiceId = await addLibraryVoice(body.publicOwnerId, body.voiceId, body.name);
  insertSavedVoice(user.accountId, {
    voiceId,
    name: body.name,
    description: body.description,
    previewUrl: body.previewUrl,
    source: "library",
  });
  return json({ ok: true, voiceId });
});
