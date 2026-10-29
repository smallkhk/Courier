import { HttpError, requireUser } from "@/lib/auth.ts";
import { billPhrase } from "@/lib/changer.ts";
import { voiceChangeStream } from "@/lib/elevenlabs.ts";
import { route } from "@/lib/http.ts";

// About 8 seconds of 16 kHz 16-bit mono audio.
const MAX_BYTES = 16000 * 2 * 8;

// Natural mode: one spoken phrase in (raw PCM), the same phrase in the
// chosen voice streamed back. A normal short request, so it works on shared
// hosting.
export const POST = route(async (request: Request) => {
  const user = await requireUser(request);
  const sessionId = new URL(request.url).searchParams.get("session") ?? "";
  const pcm = new Uint8Array(await request.arrayBuffer());
  if (pcm.length < 3200 || pcm.length > MAX_BYTES || pcm.length % 2 !== 0) {
    throw new HttpError(400, "Phrase must be between 0.1 and 8 seconds of 16 kHz audio");
  }
  const session = billPhrase(user.accountId, sessionId, (pcm.length / 2 / 16000) * 1000);
  const converted = await voiceChangeStream(session.voice_id, pcm);
  return new Response(converted.body, {
    headers: { "Content-Type": "application/octet-stream", "Cache-Control": "no-store" },
  });
});
