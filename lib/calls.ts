import { HttpError } from "./errors.ts";
import { buildCallPrompt, localTimeLabel, timeGreeting } from "./call-prompt.ts";
import { config } from "./config.ts";
import { now } from "./db.ts";
import {
  createAgent,
  getConversation,
  getSignedUrl,
  listConversations,
  updateAgent,
  type AgentSettings,
} from "./elevenlabs.ts";
import {
  abandonStaleCalls,
  getAgentId,
  getCaller,
  getCredit,
  getProfile,
  hasRecentStart,
  insertCall,
  isConversationRecorded,
  recentSummaries,
  recordConversation,
  setAgentId,
  type Call,
} from "./repo.ts";
import { isVoiceAllowed } from "./voices.ts";

export function callChargeMicros(durationSecs: number): number {
  return Math.ceil((Math.max(0, durationSecs) * config.pricePerMinuteMicros) / 60);
}

// How long a call may run on the current balance, capped by MAX_CALL_SECONDS.
export function allowedSeconds(creditMicros: number): number {
  const affordable = Math.floor((Math.max(0, creditMicros) * 60) / config.pricePerMinuteMicros);
  return Math.min(config.maxCallSeconds, affordable);
}

// Bills every finished conversation on the account's agent that hasn't been
// billed yet. ElevenLabs is the source of truth for which calls happened and
// how long they ran, so closing the tab early can't skip billing.
export async function settleAccount(accountId: string): Promise<{ settled: number; live: number; processing: number }> {
  const agentId = getAgentId(accountId);
  if (!agentId) return { settled: 0, live: 0, processing: 0 };
  let settled = 0;
  let live = 0;
  let processing = 0;
  for (const summary of await listConversations(agentId)) {
    if (summary.status === "initiated" || summary.status === "in-progress") {
      live++;
      continue;
    }
    if (summary.status === "processing") {
      processing++;
      continue;
    }
    if (isConversationRecorded(summary.conversation_id)) continue;
    const detail = await getConversation(summary.conversation_id);
    if (detail.agent_id !== agentId) continue;
    const durationSecs = detail.metadata.call_duration_secs ?? summary.call_duration_secs ?? 0;
    const recorded = recordConversation(accountId, {
      conversationId: summary.conversation_id,
      status: summary.status === "failed" ? "failed" : "done",
      startedAt: detail.metadata.start_time_unix_secs ?? summary.start_time_unix_secs,
      durationSecs,
      chargedMicros: callChargeMicros(durationSecs),
      transcript: detail.transcript
        .filter((line) => line.message)
        .map((line) => ({ role: line.role, message: line.message! })),
      summary: detail.analysis?.transcript_summary ?? "",
    });
    if (recorded) settled++;
  }
  return { settled, live, processing };
}

// Prepares the account's agent for a call with this caller and returns a
// signed URL for the browser to connect with.
export async function startCall(
  accountId: string,
  input: { callerId: string; topics: string; voiceId: string; language: string },
): Promise<{ signedUrl: string; call: Call; maxSeconds: number }> {
  const caller = getCaller(accountId, input.callerId);
  if (!caller) throw new HttpError(404, "Caller not found");
  const profile = getProfile(accountId);
  if (!profile.full_name.trim()) throw new HttpError(409, "Add your name on the My Profile page first.");
  if (!isVoiceAllowed(accountId, input.voiceId)) throw new HttpError(400, "Pick one of your voices");

  // One call at a time per account: the agent is reconfigured for each call.
  const { live } = await settleAccount(accountId);
  if (live > 0) throw new HttpError(409, "You already have a call running. End it first.");
  abandonStaleCalls(accountId, now() - 60);
  if (hasRecentStart(accountId, now() - 30)) {
    throw new HttpError(409, "A call is already connecting. Give it a few seconds.");
  }

  const maxSeconds = allowedSeconds(getCredit(accountId));
  if (maxSeconds < 60) throw new HttpError(402, "You need at least one minute of credit. Top up on the Wallet page.");

  const memory = recentSummaries(accountId, caller.id).map((m) => ({
    when: new Date(m.when * 1000).toISOString().slice(0, 10),
    summary: m.summary,
  }));
  const firstMessage =
    caller.opening === "custom"
      ? caller.custom_opening
      : caller.opening === "greet" && input.language === "en"
        ? `${timeGreeting(caller.timezone)}, ${caller.name}!`
        : "";
  const settings: AgentSettings = {
    prompt: buildCallPrompt({
      profile,
      caller,
      topics: input.topics,
      languageCode: input.language,
      localTime: localTimeLabel(caller.timezone),
      memory,
    }),
    firstMessage,
    language: input.language,
    voiceId: input.voiceId,
    maxDurationSeconds: maxSeconds,
  };

  const name = `${config.platformName} ${accountId}`;
  let agentId = getAgentId(accountId);
  if (agentId) {
    await updateAgent(agentId, name, settings);
  } else {
    agentId = await createAgent(name, settings);
    setAgentId(accountId, agentId);
  }

  const call = insertCall(accountId, caller.id, input.topics);
  return { signedUrl: await getSignedUrl(agentId), call, maxSeconds };
}
