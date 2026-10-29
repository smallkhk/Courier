import { z } from "zod";
import { requireUser } from "@/lib/auth.ts";
import { assertFastActive } from "@/lib/changer.ts";
import { singleUseToken } from "@/lib/elevenlabs.ts";
import { json, readJson, route } from "@/lib/http.ts";

const Body = z.object({ sessionId: z.string().min(1).max(100), kind: z.enum(["stt", "tts"]) });

// Fast mode: a fresh single-use token to reconnect a dropped ElevenLabs
// connection, only while the session has paid time left.
export const POST = route(async (request: Request) => {
  const user = await requireUser(request);
  const { sessionId, kind } = await readJson(request, Body);
  assertFastActive(user.accountId, sessionId);
  return json({ token: await singleUseToken(kind === "stt" ? "realtime_scribe" : "tts_websocket") });
});
