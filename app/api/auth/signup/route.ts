import { z } from "zod";
import { createAccount, findUserByEmail, HttpError, isBootstrapAdmin, startSession } from "@/lib/auth.ts";
import { config } from "@/lib/config.ts";
import { json, readJson, route } from "@/lib/http.ts";

const Body = z.object({
  email: z.string().trim().toLowerCase().email("Enter a valid email"),
  password: z.string().min(10, "Password must be at least 10 characters").max(200),
});

export const POST = route(async (request: Request) => {
  const body = await readJson(request, Body);
  if (!config.signupsOpen && !isBootstrapAdmin(body.email)) {
    throw new HttpError(403, "Sign-ups are closed right now. Please check back later.");
  }
  if (findUserByEmail(body.email)) throw new HttpError(409, "An account with that email already exists");
  const userId = createAccount(body.email, body.password);
  await startSession(userId);
  return json({ ok: true });
});
