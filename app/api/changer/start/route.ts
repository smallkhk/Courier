import { z } from "zod";
import { requireUser } from "@/lib/auth.ts";
import { BLOCK_MS, pricePerMinute, startSession } from "@/lib/changer.ts";
import { config } from "@/lib/config.ts";
import { ELEVENLABS_BASE, singleUseToken } from "@/lib/elevenlabs.ts";
import { json, readJson, route } from "@/lib/http.ts";
import { getCredit } from "@/lib/repo.ts";

const Body = z.object({
  mode: z.enum(["fast", "natural"]),
  voiceId: z.string().min(1).max(200),
});

// Starts a voice changer session. Fast mode also gets single-use tokens so
// the browser can connect straight to ElevenLabs.
export const POST = route(async (request: Request) => {
  const user = await requireUser(request);
  const { mode, voiceId } = await readJson(request, Body);
  const session = startSession(user.accountId, mode, voiceId);
  const base = {
    sessionId: session.id,
    mode,
    pricePerMinuteMicros: pricePerMinute(mode),
    creditMicros: getCredit(user.accountId),
  };
  if (mode === "natural") return json(base);
  const [sttToken, ttsToken] = await Promise.all([singleUseToken("realtime_scribe"), singleUseToken("tts_websocket")]);
  return json({
    ...base,
    sttToken,
    ttsToken,
    wsBase: ELEVENLABS_BASE.replace(/^http/, "ws"),
    voiceId,
    ttsModel: config.ttsModel,
    paidUntilMs: session.paid_until_ms,
    blockMs: BLOCK_MS,
    serverNowMs: Date.now(),
  });
});
