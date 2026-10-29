import { z } from "zod";
import { requireUser } from "@/lib/auth.ts";
import { json, readJson, route } from "@/lib/http.ts";
import { saveCallSettings } from "@/lib/repo.ts";

const Body = z.object({
  voice_speed: z.number().min(0.7).max(1.2),
  voice_stability: z.number().min(0).max(1),
  voice_similarity: z.number().min(0).max(1),
  turn_eagerness: z.enum(["patient", "normal", "eager"]),
  turn_timeout: z.number().int().min(3).max(20),
  auto_hangup: z.boolean(),
});

export const PUT = route(async (request: Request) => {
  const user = await requireUser(request);
  const body = await readJson(request, Body);
  saveCallSettings(user.accountId, { ...body, auto_hangup: body.auto_hangup ? 1 : 0 });
  return json({ ok: true });
});
