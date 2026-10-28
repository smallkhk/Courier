import { endSession } from "@/lib/auth.ts";
import { json, route } from "@/lib/http.ts";

export const POST = route(async () => {
  await endSession();
  return json({ ok: true });
});
