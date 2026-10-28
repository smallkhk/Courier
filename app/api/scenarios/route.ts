import { requireUser } from "@/lib/auth.ts";
import { json, readJson, route } from "@/lib/http.ts";
import { listScenarios } from "@/lib/repo.ts";
import { createScenario, publicScenario, ScenarioInput } from "@/lib/scenarios.ts";

export const GET = route(async () => {
  const user = await requireUser();
  return json({ scenarios: listScenarios(user.accountId).map(publicScenario) });
});

export const POST = route(async (request: Request) => {
  const user = await requireUser(request);
  const fields = await readJson(request, ScenarioInput);
  const scenario = await createScenario(user.accountId, fields);
  return json({ scenario: publicScenario(scenario) }, 201);
});
