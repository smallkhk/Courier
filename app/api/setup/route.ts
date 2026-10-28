import { z } from "zod";
import { HttpError, requireUser } from "@/lib/auth.ts";
import { streamSetupReply } from "@/lib/claude.ts";
import { readJson, route } from "@/lib/http.ts";
import { getCredit } from "@/lib/repo.ts";

const Body = z.object({
  messages: z
    .array(z.object({ role: z.enum(["user", "assistant"]), content: z.string().min(1).max(20_000) }))
    .min(1)
    .max(40, "This chat is getting long. Save the scenario or start a new one.")
    .refine((m) => m[0].role === "user" && m[m.length - 1].role === "user", "Conversation must start and end with a user message"),
});

export const POST = route(async (request: Request) => {
  const user = await requireUser(request);
  const { messages } = await readJson(request, Body);
  if (getCredit(user.accountId) <= 0) throw new HttpError(402, "You're out of credit. Top up to keep going.");
  return new Response(streamSetupReply(user.accountId, messages), {
    headers: { "Content-Type": "text/plain; charset=utf-8", "Cache-Control": "no-store" },
  });
});
