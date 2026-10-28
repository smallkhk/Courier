import { z } from "zod";
import { adjustCredit, deleteUser, setAdmin, setBlocked, setPassword, signOutEverywhere } from "@/lib/admin.ts";
import { requireAdmin } from "@/lib/auth.ts";
import { json, readJson, route } from "@/lib/http.ts";

type Ctx = { params: Promise<{ id: string }> };

const Body = z.discriminatedUnion("action", [
  z.object({ action: z.literal("credit"), amount: z.number(), reason: z.string().max(200).default("") }),
  z.object({ action: z.literal("block"), reason: z.string().max(200).default("") }),
  z.object({ action: z.literal("unblock") }),
  z.object({ action: z.literal("make_admin") }),
  z.object({ action: z.literal("remove_admin") }),
  z.object({ action: z.literal("set_password"), password: z.string().min(10, "Password must be at least 10 characters").max(200) }),
  z.object({ action: z.literal("sign_out") }),
  z.object({ action: z.literal("delete"), confirm: z.literal("DELETE", { message: 'Type DELETE to confirm' }) }),
]);

export const POST = route(async (request: Request, { params }: Ctx) => {
  const admin = await requireAdmin(request);
  const { id } = await params;
  const body = await readJson(request, Body);
  switch (body.action) {
    case "credit":
      adjustCredit(admin.email, id, body.amount, body.reason);
      break;
    case "block":
    case "unblock":
      setBlocked(admin.email, admin.userId, id, body.action === "block", body.action === "block" ? body.reason : "");
      break;
    case "make_admin":
    case "remove_admin":
      setAdmin(admin.email, admin.userId, id, body.action === "make_admin");
      break;
    case "set_password":
      setPassword(admin.email, id, body.password);
      break;
    case "sign_out":
      signOutEverywhere(admin.email, id);
      break;
    case "delete":
      await deleteUser(admin.email, admin.userId, id);
      break;
  }
  return json({ ok: true });
});
