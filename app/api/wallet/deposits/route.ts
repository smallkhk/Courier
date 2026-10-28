import { z } from "zod";
import { HttpError, requireUser } from "@/lib/auth.ts";
import { json, readJson, route } from "@/lib/http.ts";
import { createDeposit, PaymentError } from "@/lib/payments.ts";
import { listDeposits } from "@/lib/repo.ts";

const Body = z.object({
  network: z.enum(["trc20", "bep20"]),
  amount: z.number().int("Enter a whole number"),
});

export const GET = route(async () => {
  const user = await requireUser();
  return json({ deposits: listDeposits(user.accountId) });
});

export const POST = route(async (request: Request) => {
  const user = await requireUser(request);
  const body = await readJson(request, Body);
  try {
    return json({ deposit: createDeposit(user.accountId, body.network, body.amount) }, 201);
  } catch (err) {
    if (err instanceof PaymentError) throw new HttpError(400, err.message);
    throw err;
  }
});
