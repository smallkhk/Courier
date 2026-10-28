import { HttpError, requireUser } from "@/lib/auth.ts";
import { json, readJson, route } from "@/lib/http.ts";
import { getScenario, listSessions } from "@/lib/repo.ts";
import { editScenario, publicScenario, removeScenario, ScenarioInput } from "@/lib/scenarios.ts";

type Ctx = { params: Promise<{ id: string }> };

export const GET = route(async (_request: Request, { params }: Ctx) => {
  const user = await requireUser();
  const { id } = await params;
  const scenario = getScenario(user.accountId, id);
  if (!scenario) throw new HttpError(404, "Scenario not found");
  return json({ scenario: publicScenario(scenario), sessions: listSessions(user.accountId, id) });
});

export const PUT = route(async (request: Request, { params }: Ctx) => {
  const user = await requireUser(request);
  const { id } = await params;
  const fields = await readJson(request, ScenarioInput);
  const scenario = await editScenario(user.accountId, id, fields);
  return json({ scenario: publicScenario(scenario) });
});

export const DELETE = route(async (request: Request, { params }: Ctx) => {
  const user = await requireUser(request);
  const { id } = await params;
  await removeScenario(user.accountId, id);
  return json({ ok: true });
});
