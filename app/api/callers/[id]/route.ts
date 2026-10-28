import { HttpError, requireUser } from "@/lib/auth.ts";
import { CallerInput } from "@/lib/caller-input.ts";
import { json, readJson, route } from "@/lib/http.ts";
import { deleteCaller, getCaller, updateCaller } from "@/lib/repo.ts";
import { isVoiceAllowed } from "@/lib/voices.ts";

type Ctx = { params: Promise<{ id: string }> };

export const PUT = route(async (request: Request, { params }: Ctx) => {
  const user = await requireUser(request);
  const { id } = await params;
  const fields = await readJson(request, CallerInput);
  if (!isVoiceAllowed(user.accountId, fields.voice_id)) throw new HttpError(400, "Pick one of your voices");
  if (!updateCaller(user.accountId, id, fields)) throw new HttpError(404, "Caller not found");
  return json({ caller: getCaller(user.accountId, id) });
});

export const DELETE = route(async (request: Request, { params }: Ctx) => {
  const user = await requireUser(request);
  const { id } = await params;
  deleteCaller(user.accountId, id);
  return json({ ok: true });
});
