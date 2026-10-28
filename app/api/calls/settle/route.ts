import { z } from "zod";
import { requireUser } from "@/lib/auth.ts";
import { settleAccount } from "@/lib/calls.ts";
import { json, readJson, route } from "@/lib/http.ts";
import { getCredit, listCalls } from "@/lib/repo.ts";

const Body = z.object({ callerId: z.string().optional() });

// Bills finished calls and returns the caller's call history. The call page
// polls this after a call ends while ElevenLabs finalises the conversation.
export const POST = route(async (request: Request) => {
  const user = await requireUser(request);
  const { callerId } = await readJson(request, Body);
  const result = await settleAccount(user.accountId);
  return json({
    ...result,
    creditMicros: getCredit(user.accountId),
    calls: listCalls(user.accountId, { callerId, limit: 20 }),
  });
});
