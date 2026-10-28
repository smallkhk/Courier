import { HttpError, requireUser } from "@/lib/auth.ts";
import { canStartCall } from "@/lib/billing.ts";
import { getSignedUrl } from "@/lib/elevenlabs.ts";
import { json, route } from "@/lib/http.ts";
import { getScenario } from "@/lib/repo.ts";

type Ctx = { params: Promise<{ id: string }> };

// Hands the browser a short-lived signed URL for this scenario's agent. The
// prompt and voice live on the agent, so nothing the browser sends can change
// what the voice AI is told.
export const POST = route(async (request: Request, { params }: Ctx) => {
  const user = await requireUser(request);
  const { id } = await params;
  const scenario = getScenario(user.accountId, id);
  if (!scenario) throw new HttpError(404, "Scenario not found");
  if (!scenario.agent_id) throw new HttpError(409, "Voice isn't set up for this scenario yet. Save it again once ElevenLabs is configured.");
  if (!canStartCall(user.accountId)) throw new HttpError(402, "You need at least one minute of credit to start a call.");
  return json({ signedUrl: await getSignedUrl(scenario.agent_id) });
});
