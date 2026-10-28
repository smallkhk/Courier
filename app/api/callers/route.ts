import { HttpError, requireUser } from "@/lib/auth.ts";
import { CallerInput } from "@/lib/caller-input.ts";
import { json, readJson, route } from "@/lib/http.ts";
import { insertCaller } from "@/lib/repo.ts";
import { isVoiceAllowed } from "@/lib/voices.ts";

export const POST = route(async (request: Request) => {
  const user = await requireUser(request);
  const fields = await readJson(request, CallerInput);
  if (!isVoiceAllowed(user.accountId, fields.voice_id)) throw new HttpError(400, "Pick one of your voices");
  return json({ caller: insertCaller(user.accountId, fields) }, 201);
});
