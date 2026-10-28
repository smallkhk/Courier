import { HttpError, requireUser } from "@/lib/auth.ts";
import { settleScenario } from "@/lib/billing.ts";
import { json, route } from "@/lib/http.ts";
import { getCredit, getScenario, listSessions } from "@/lib/repo.ts";

type Ctx = { params: Promise<{ id: string }> };

// Called after a call ends (and whenever the scenario page loads) to bill
// finished calls and pull their transcripts.
export const POST = route(async (request: Request, { params }: Ctx) => {
  const user = await requireUser(request);
  const { id } = await params;
  const scenario = getScenario(user.accountId, id);
  if (!scenario) throw new HttpError(404, "Scenario not found");
  const result = await settleScenario(scenario);
  return json({
    ...result,
    creditMicros: getCredit(user.accountId),
    sessions: listSessions(user.accountId, id),
  });
});
