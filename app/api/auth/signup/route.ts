import { z } from "zod";
import { createAccount, findUserByEmail, HttpError, startSession } from "@/lib/auth.ts";
import { json, readJson, route } from "@/lib/http.ts";

const Body = z.object({
  email: z.string().trim().toLowerCase().email("Enter a valid email"),
  password: z.string().min(10, "Password must be at least 10 characters").max(200),
});

export const POST = route(async (request: Request) => {
  const body = await readJson(request, Body);
  if (findUserByEmail(body.email)) throw new HttpError(409, "An account with that email already exists");
  const userId = createAccount(body.email, body.password);
  await startSession(userId);
  return json({ ok: true });
});
