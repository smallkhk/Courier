import { config } from "./config.ts";
import { getConversation, listConversations } from "./elevenlabs.ts";
import { getCredit, isConversationRecorded, recordCall, type Scenario } from "./repo.ts";

// Voice is billed per started second at the customer price per minute.
export function voiceChargeMicros(durationSecs: number): number {
  return Math.ceil((Math.max(0, durationSecs) * config.pricePerMinuteMicros) / 60);
}

// A call may start only if the account can pay for at least one minute.
export function canStartCall(accountId: string): boolean {
  return getCredit(accountId) >= voiceChargeMicros(60);
}

// Bills every finished call on the scenario's agent that hasn't been billed
// yet. ElevenLabs is the source of truth for which calls happened and how
// long they lasted, so a client that never reports back can't skip billing.
// Returns how many calls are still being processed by ElevenLabs.
export async function settleScenario(scenario: Scenario): Promise<{ settled: number; pending: number }> {
  if (!scenario.agent_id) return { settled: 0, pending: 0 };
  const conversations = await listConversations(scenario.agent_id);
  let settled = 0;
  let pending = 0;
  for (const summary of conversations) {
    if (summary.status !== "done" && summary.status !== "failed") {
      pending++;
      continue;
    }
    if (isConversationRecorded(summary.conversation_id)) continue;
    const detail = await getConversation(summary.conversation_id);
    if (detail.agent_id !== scenario.agent_id) continue;
    const durationSecs = detail.metadata.call_duration_secs ?? summary.call_duration_secs ?? 0;
    const recorded = recordCall(scenario.account_id, {
      scenarioId: scenario.id,
      conversationId: summary.conversation_id,
      status: detail.status,
      durationSecs,
      chargedMicros: voiceChargeMicros(durationSecs),
      transcript: detail.transcript
        .filter((line) => line.message)
        .map((line) => ({ role: line.role, message: line.message! })),
      startedAt: summary.start_time_unix_secs,
    });
    if (recorded) settled++;
  }
  return { settled, pending };
}
