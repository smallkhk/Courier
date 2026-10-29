import { z } from "zod";
import { requireUser } from "@/lib/auth.ts";
import { stopSession } from "@/lib/changer.ts";
import { json, readJson, route } from "@/lib/http.ts";
import { getCredit } from "@/lib/repo.ts";

const Body = z.object({ sessionId: z.string().min(1).max(100) });

// Ends a session; fast mode gets its unused prepaid time back.
export const POST = route(async (request: Request) => {
  const user = await requireUser(request);
  const { sessionId } = await readJson(request, Body);
  const { refundedMicros } = stopSession(user.accountId, sessionId);
  return json({ refundedMicros, creditMicros: getCredit(user.accountId) });
});
