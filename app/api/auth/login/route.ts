import { z } from "zod";
import { findUserByEmail, HttpError, startSession, verifyPassword } from "@/lib/auth.ts";
import { json, readJson, route } from "@/lib/http.ts";

const Body = z.object({
  email: z.string().trim().toLowerCase(),
  password: z.string(),
});

export const POST = route(async (request: Request) => {
  const body = await readJson(request, Body);
  const user = findUserByEmail(body.email);
  if (!user || !verifyPassword(body.password, user.password_hash)) {
    throw new HttpError(401, "Wrong email or password");
  }
  if (user.blocked_at !== null) {
    throw new HttpError(403, `This account is suspended.${user.block_reason ? ` Reason: ${user.block_reason}` : ""}`);
  }
  await startSession(user.id);
  return json({ ok: true });
});
