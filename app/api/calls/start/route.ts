import { z } from "zod";
import { requireUser } from "@/lib/auth.ts";
import { startCall } from "@/lib/calls.ts";
import { json, readJson, route } from "@/lib/http.ts";
import { isLanguage } from "@/lib/languages.ts";

const Body = z.object({
  callerId: z.string().min(1),
  topics: z.string().trim().max(4000),
  voiceId: z.string().min(1),
  language: z.string().refine(isLanguage, "Pick a language from the list"),
});

export const POST = route(async (request: Request) => {
  const user = await requireUser(request);
  const result = await startCall(user.accountId, await readJson(request, Body));
  return json({ signedUrl: result.signedUrl, callId: result.call.id, maxSeconds: result.maxSeconds });
});
