import { z } from "zod";
import { logAdmin } from "@/lib/admin.ts";
import { HttpError, requireAdmin } from "@/lib/auth.ts";
import { getDb } from "@/lib/db.ts";
import { json, readJson, route } from "@/lib/http.ts";
import { approveReview, markDepositPaid, PaymentError, rejectDeposit } from "@/lib/payments.ts";

type Ctx = { params: Promise<{ id: string }> };

const Body = z.discriminatedUnion("action", [
  z.object({ action: z.literal("approve") }),
  z.object({ action: z.literal("reject"), reason: z.string().max(200).default("") }),
  z.object({ action: z.literal("mark_paid"), amount: z.number().positive("Enter the amount received"), hash: z.string().max(100).default("") }),
]);

function emailFor(depositId: string): string {
  const r = getDb()
    .prepare("SELECT u.email FROM deposits d JOIN users u ON u.account_id = d.account_id WHERE d.id = ?")
    .get(depositId) as { email: string } | undefined;
  return r?.email ?? depositId;
}

export const POST = route(async (request: Request, { params }: Ctx) => {
  const admin = await requireAdmin(request);
  const { id } = await params;
  const body = await readJson(request, Body);
  try {
    switch (body.action) {
      case "approve":
        approveReview(id);
        logAdmin(admin.email, "topup.approve", emailFor(id), id);
        break;
      case "reject":
        rejectDeposit(id, body.reason);
        logAdmin(admin.email, "topup.reject", emailFor(id), `${id} · ${body.reason || "no reason"}`);
        break;
      case "mark_paid": {
        markDepositPaid(id, body.amount, body.hash);
        logAdmin(admin.email, "topup.mark_paid", emailFor(id), `${id} · ${body.amount} USDT${body.hash ? ` · ${body.hash}` : ""}`);
        break;
      }
    }
  } catch (err) {
    if (err instanceof PaymentError) throw new HttpError(400, err.message);
    throw err;
  }
  return json({ ok: true });
});
