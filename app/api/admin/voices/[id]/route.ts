import { removeVoice } from "@/lib/admin.ts";
import { requireAdmin } from "@/lib/auth.ts";
import { json, route } from "@/lib/http.ts";

type Ctx = { params: Promise<{ id: string }> };

export const DELETE = route(async (request: Request, { params }: Ctx) => {
  const admin = await requireAdmin(request);
  const { id } = await params;
  await removeVoice(admin.email, id);
  return json({ ok: true });
});
