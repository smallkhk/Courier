import { z } from "zod";
import { requireUser } from "@/lib/auth.ts";
import { json, readJson, route } from "@/lib/http.ts";
import { saveProfile } from "@/lib/repo.ts";

const Body = z.object({
  full_name: z.string().trim().min(1, "Enter your name").max(100),
  nickname: z.string().trim().max(60),
  location: z.string().trim().max(120),
  about: z.string().trim().max(4000),
  speaking_style: z.string().trim().max(2000),
});

export const PUT = route(async (request: Request) => {
  const user = await requireUser(request);
  saveProfile(user.accountId, await readJson(request, Body));
  return json({ ok: true });
});
