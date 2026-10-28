// Thin wrapper over the ElevenLabs Agents REST API. Each saved scenario gets
// its own agent whose prompt and voice are set here on the server, so the
// browser only ever receives a short-lived signed URL and cannot swap in a
// prompt that skipped the save-time safety check.
import { config } from "./config.ts";
import { buildAgentPrompt } from "./prompts.ts";

const BASE = "https://api.elevenlabs.io/v1";

export class ElevenLabsError extends Error {}

export function elevenLabsConfigured(): boolean {
  return Boolean(process.env.ELEVENLABS_API_KEY);
}

async function call<T>(path: string, init: RequestInit = {}): Promise<T> {
  const key = process.env.ELEVENLABS_API_KEY;
  if (!key) throw new ElevenLabsError("ELEVENLABS_API_KEY is not set");
  const response = await fetch(`${BASE}${path}`, {
    ...init,
    headers: { "xi-api-key": key, "Content-Type": "application/json", ...init.headers },
    cache: "no-store",
  });
  if (!response.ok) {
    const body = await response.text().catch(() => "");
    throw new ElevenLabsError(`ElevenLabs ${init.method ?? "GET"} ${path} failed: ${response.status} ${body.slice(0, 300)}`);
  }
  if (response.status === 204) return undefined as T;
  return (await response.json()) as T;
}

type AgentFields = { name: string; prompt: string; firstMessage: string; voiceId: string };

function agentBody(tag: string, fields: AgentFields) {
  return {
    name: `${fields.name.slice(0, 80)} [${tag}]`,
    conversation_config: {
      agent: {
        first_message: fields.firstMessage,
        language: "en",
        prompt: { prompt: buildAgentPrompt(fields.prompt) },
      },
      tts: { voice_id: fields.voiceId },
      conversation: { max_duration_seconds: config.maxCallSeconds },
    },
  };
}

// tag identifies the scenario in the ElevenLabs dashboard (account:scenario).
export async function createAgent(tag: string, fields: AgentFields): Promise<string> {
  const result = await call<{ agent_id: string }>("/convai/agents/create", {
    method: "POST",
    body: JSON.stringify(agentBody(tag, fields)),
  });
  return result.agent_id;
}

export async function updateAgent(agentId: string, tag: string, fields: AgentFields): Promise<void> {
  await call(`/convai/agents/${encodeURIComponent(agentId)}`, {
    method: "PATCH",
    body: JSON.stringify(agentBody(tag, fields)),
  });
}

export async function deleteAgent(agentId: string): Promise<void> {
  await call(`/convai/agents/${encodeURIComponent(agentId)}`, { method: "DELETE" });
}

export async function getSignedUrl(agentId: string): Promise<string> {
  const result = await call<{ signed_url: string }>(
    `/convai/conversation/get-signed-url?agent_id=${encodeURIComponent(agentId)}`,
  );
  return result.signed_url;
}

export type ConversationSummary = {
  conversation_id: string;
  status: "initiated" | "in-progress" | "processing" | "done" | "failed";
  call_duration_secs: number;
  start_time_unix_secs: number;
};

export async function listConversations(agentId: string, maxPages = 10): Promise<ConversationSummary[]> {
  const all: ConversationSummary[] = [];
  let cursor: string | null | undefined;
  for (let page = 0; page < maxPages; page++) {
    const query = new URLSearchParams({ agent_id: agentId, page_size: "100" });
    if (cursor) query.set("cursor", cursor);
    const result = await call<{
      conversations: ConversationSummary[];
      has_more: boolean;
      next_cursor?: string | null;
    }>(`/convai/conversations?${query}`);
    all.push(...result.conversations);
    if (!result.has_more || !result.next_cursor) break;
    cursor = result.next_cursor;
  }
  return all;
}

export type ConversationDetail = {
  agent_id: string;
  status: ConversationSummary["status"];
  transcript: { role: "user" | "agent"; message?: string | null }[];
  metadata: { call_duration_secs: number; start_time_unix_secs?: number };
};

export async function getConversation(conversationId: string): Promise<ConversationDetail> {
  return call<ConversationDetail>(`/convai/conversations/${encodeURIComponent(conversationId)}`);
}
