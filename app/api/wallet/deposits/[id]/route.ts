import { z } from "zod";
import { HttpError, requireUser } from "@/lib/auth.ts";
import { json, readJson, route } from "@/lib/http.ts";
import { claimByHash, PaymentError, scanPayments } from "@/lib/payments.ts";
import { getCredit, getDeposit } from "@/lib/repo.ts";

type Ctx = { params: Promise<{ id: string }> };

let lastScan = 0;

// Polled by the wallet page while a top-up is open. Scans the chains at most
// every 15 seconds across all users; the watcher script does the same in the
// background.
export const GET = route(async (_request: Request, { params }: Ctx) => {
  const user = await requireUser();
  const { id } = await params;
  const deposit = getDeposit(user.accountId, id);
  if (!deposit) throw new HttpError(404, "Top-up not found");
  if (deposit.status === "pending" && Date.now() - lastScan > 15_000) {
    lastScan = Date.now();
    const { errors } = await scanPayments();
    if (errors.length) console.warn("payment scan:", errors.join("; "));
  }
  return json({ deposit: getDeposit(user.accountId, id), creditMicros: getCredit(user.accountId) });
});

const Body = z.object({ hash: z.string().min(10).max(100) });

export const POST = route(async (request: Request, { params }: Ctx) => {
  const user = await requireUser(request);
  const { id } = await params;
  const { hash } = await readJson(request, Body);
  try {
    const deposit = await claimByHash(user.accountId, id, hash);
    return json({ deposit, creditMicros: getCredit(user.accountId) });
  } catch (err) {
    if (err instanceof PaymentError) throw new HttpError(400, err.message);
    throw err;
  }
});
