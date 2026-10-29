import { z } from "zod";
import { requireUser } from "@/lib/auth.ts";
import { renewFast } from "@/lib/changer.ts";
import { json, readJson, route } from "@/lib/http.ts";
import { getCredit } from "@/lib/repo.ts";

const Body = z.object({ sessionId: z.string().min(1).max(100) });

// Fast mode: prepay the next minute while the changer keeps running.
export const POST = route(async (request: Request) => {
  const user = await requireUser(request);
  const { sessionId } = await readJson(request, Body);
  const session = renewFast(user.accountId, sessionId);
  return json({ paidUntilMs: session.paid_until_ms, serverNowMs: Date.now(), creditMicros: getCredit(user.accountId) });
});
