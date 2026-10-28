import { requireUser } from "@/lib/auth.ts";
import { config } from "@/lib/config.ts";
import { json, route } from "@/lib/http.ts";
import { getCredit, listUsage } from "@/lib/repo.ts";

export const GET = route(async () => {
  const user = await requireUser();
  return json({
    email: user.email,
    creditMicros: getCredit(user.accountId),
    pricePerMinuteMicros: config.pricePerMinuteMicros,
    usage: listUsage(user.accountId),
  });
});
