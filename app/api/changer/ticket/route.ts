import { headers } from "next/headers";
import { z } from "zod";
import { requireUser } from "@/lib/auth.ts";
import { createTicket, pricePerMinute, relayUrlFor } from "@/lib/changer.ts";
import { json, readJson, route } from "@/lib/http.ts";

const Body = z.object({
  mode: z.enum(["fast", "natural"]),
  voiceId: z.string().min(1).max(200),
});

// Gives the browser or desktop app a one-time ticket to open a voice changer
// session on the relay.
export const POST = route(async (request: Request) => {
  const user = await requireUser(request);
  const { mode, voiceId } = await readJson(request, Body);
  const { ticket } = createTicket(user.accountId, mode, voiceId);
  const h = await headers();
  const secure = (h.get("x-forwarded-proto") ?? new URL(request.url).protocol.replace(":", "")) === "https";
  return json({ ticket, relayUrl: relayUrlFor(h.get("host"), secure), pricePerMinuteMicros: pricePerMinute(mode) });
});
