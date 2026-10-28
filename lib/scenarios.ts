import { z } from "zod";
import { HttpError } from "./auth.ts";
import { checkScenario } from "./claude.ts";
import { createAgent, deleteAgent, elevenLabsConfigured, updateAgent } from "./elevenlabs.ts";
import {
  deleteScenario,
  getScenario,
  insertScenario,
  setScenarioAgent,
  updateScenario,
  type Scenario,
} from "./repo.ts";
import { settleScenario } from "./billing.ts";
import { isAllowedVoice } from "./voices.ts";

export const ScenarioInput = z.object({
  name: z.string().trim().min(1, "Give the scenario a name").max(100),
  prompt: z.string().trim().min(20, "The scenario prompt is too short").max(20_000),
  firstMessage: z.string().trim().max(500).default(""),
  voiceId: z.string().refine(isAllowedVoice, "Pick a voice from the list"),
});

export type ScenarioFields = z.infer<typeof ScenarioInput>;

async function ensureAllowed(accountId: string, fields: ScenarioFields): Promise<void> {
  const check = await checkScenario(accountId, fields);
  if (!check.allowed) throw new HttpError(422, check.reason);
}

async function syncAgent(scenario: Scenario, fields: ScenarioFields): Promise<void> {
  if (!elevenLabsConfigured()) return;
  const tag = `${scenario.account_id}:${scenario.id}`;
  if (scenario.agent_id) {
    await updateAgent(scenario.agent_id, tag, fields);
  } else {
    const agentId = await createAgent(tag, fields);
    setScenarioAgent(scenario.account_id, scenario.id, agentId);
  }
}

// Every save runs the boundary check first, then pushes the prompt to the
// scenario's voice agent. Nothing reaches the voice agent without passing.
export async function createScenario(accountId: string, fields: ScenarioFields): Promise<Scenario> {
  await ensureAllowed(accountId, fields);
  const scenario = insertScenario(accountId, fields);
  await syncAgent(scenario, fields);
  return getScenario(accountId, scenario.id)!;
}

export async function editScenario(accountId: string, id: string, fields: ScenarioFields): Promise<Scenario> {
  const existing = getScenario(accountId, id);
  if (!existing) throw new HttpError(404, "Scenario not found");
  await ensureAllowed(accountId, fields);
  updateScenario(accountId, id, fields);
  await syncAgent(existing, fields);
  return getScenario(accountId, id)!;
}

export async function removeScenario(accountId: string, id: string): Promise<void> {
  const existing = getScenario(accountId, id);
  if (!existing) throw new HttpError(404, "Scenario not found");
  if (existing.agent_id) {
    // Bill any finished calls before the agent (and its call history) is gone.
    const { pending } = await settleScenario(existing);
    if (pending > 0) throw new HttpError(409, "A practice call is still running or processing. Try again in a minute.");
    await deleteAgent(existing.agent_id);
  }
  deleteScenario(accountId, id);
}

export function publicScenario(scenario: Scenario) {
  return {
    id: scenario.id,
    name: scenario.name,
    prompt: scenario.prompt,
    firstMessage: scenario.first_message,
    voiceId: scenario.voice_id,
    voiceReady: Boolean(scenario.agent_id),
    updatedAt: scenario.updated_at,
  };
}
