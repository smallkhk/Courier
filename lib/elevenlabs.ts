// Thin wrapper over the ElevenLabs REST API. Each account has one agent. Its
// prompt, voice and limits are set here on the server right before every
// call, so the browser only ever receives a short-lived signed URL and can't
// change what the AI is told.
import { config } from "./config.ts";

// ELEVENLABS_API_BASE lets tests point the app at a local stand-in.
export const ELEVENLABS_BASE = process.env.ELEVENLABS_API_BASE || "https://api.elevenlabs.io";
const BASE = `${ELEVENLABS_BASE}/v1`;

export class ElevenLabsError extends Error {}

export function elevenLabsConfigured(): boolean {
  return Boolean(process.env.ELEVENLABS_API_KEY);
}

function key(): string {
  const value = process.env.ELEVENLABS_API_KEY;
  if (!value) throw new ElevenLabsError("ELEVENLABS_API_KEY is not set");
  return value;
}

async function call<T>(path: string, init: RequestInit = {}): Promise<T> {
  const isForm = init.body instanceof FormData;
  const response = await fetch(`${BASE}${path}`, {
    ...init,
    headers: { "xi-api-key": key(), ...(isForm ? {} : { "Content-Type": "application/json" }), ...init.headers },
    cache: "no-store",
  });
  if (!response.ok) {
    const body = await response.text().catch(() => "");
    throw new ElevenLabsError(`ElevenLabs ${init.method ?? "GET"} ${path} failed: ${response.status} ${body.slice(0, 300)}`);
  }
  if (response.status === 204) return undefined as T;
  return (await response.json()) as T;
}

// ------------------------------------------------------------------- agent

export type AgentSettings = {
  prompt: string;
  firstMessage: string;
  language: string;
  voiceId: string;
  maxDurationSeconds: number;
};

function agentBody(name: string, s: AgentSettings) {
  return {
    name,
    conversation_config: {
      agent: {
        first_message: s.firstMessage,
        language: s.language,
        prompt: { prompt: s.prompt, llm: config.agentLlm },
      },
      tts: { voice_id: s.voiceId, model_id: config.ttsModel },
      conversation: { max_duration_seconds: s.maxDurationSeconds },
    },
  };
}

export async function createAgent(name: string, s: AgentSettings): Promise<string> {
  const result = await call<{ agent_id: string }>("/convai/agents/create", {
    method: "POST",
    body: JSON.stringify(agentBody(name, s)),
  });
  return result.agent_id;
}

export async function updateAgent(agentId: string, name: string, s: AgentSettings): Promise<void> {
  await call(`/convai/agents/${encodeURIComponent(agentId)}`, {
    method: "PATCH",
    body: JSON.stringify(agentBody(name, s)),
  });
}

export async function getSignedUrl(agentId: string): Promise<string> {
  const result = await call<{ signed_url: string }>(
    `/convai/conversation/get-signed-url?agent_id=${encodeURIComponent(agentId)}`,
  );
  return result.signed_url;
}

// ----------------------------------------------------------- conversations

export type ConversationStatus = "initiated" | "in-progress" | "processing" | "done" | "failed";

export type ConversationSummary = {
  conversation_id: string;
  status: ConversationStatus;
  call_duration_secs: number;
  start_time_unix_secs: number;
};

export async function listConversations(agentId: string, maxPages = 5): Promise<ConversationSummary[]> {
  const all: ConversationSummary[] = [];
  let cursor: string | null | undefined;
  for (let page = 0; page < maxPages; page++) {
    const query = new URLSearchParams({ agent_id: agentId, page_size: "100" });
    if (cursor) query.set("cursor", cursor);
    const result = await call<{ conversations: ConversationSummary[]; has_more: boolean; next_cursor?: string | null }>(
      `/convai/conversations?${query}`,
    );
    all.push(...result.conversations);
    if (!result.has_more || !result.next_cursor) break;
    cursor = result.next_cursor;
  }
  return all;
}

export type ConversationDetail = {
  agent_id: string;
  status: ConversationStatus;
  transcript: { role: "user" | "agent"; message?: string | null }[];
  metadata: { call_duration_secs: number; start_time_unix_secs: number };
  analysis?: { transcript_summary?: string } | null;
};

export async function getConversation(conversationId: string): Promise<ConversationDetail> {
  return call<ConversationDetail>(`/convai/conversations/${encodeURIComponent(conversationId)}`);
}

// ------------------------------------------------------------------ voices

export type LibraryVoice = {
  public_owner_id: string;
  voice_id: string;
  name: string;
  accent: string;
  gender: string;
  age: string;
  descriptive: string;
  use_case: string;
  language: string;
  preview_url: string;
  description: string;
};

export async function searchLibrary(filters: {
  search?: string;
  gender?: string;
  accent?: string;
  language?: string;
  page?: number;
}): Promise<{ voices: LibraryVoice[]; hasMore: boolean }> {
  const query = new URLSearchParams({ page_size: "24", page: String(filters.page ?? 0), sort: "trending" });
  for (const field of ["search", "gender", "accent", "language"] as const) {
    if (filters[field]) query.set(field, filters[field]!);
  }
  const result = await call<{ voices: LibraryVoice[]; has_more: boolean }>(`/shared-voices?${query}`);
  return { voices: result.voices, hasMore: result.has_more };
}

// Copies a library voice into our ElevenLabs account so agents can use it.
export async function addLibraryVoice(publicOwnerId: string, voiceId: string, name: string): Promise<string> {
  const result = await call<{ voice_id: string }>(
    `/voices/add/${encodeURIComponent(publicOwnerId)}/${encodeURIComponent(voiceId)}`,
    { method: "POST", body: JSON.stringify({ new_name: name.slice(0, 100) }) },
  );
  return result.voice_id;
}

export async function cloneVoice(name: string, description: string, sample: Blob, filename: string): Promise<string> {
  const form = new FormData();
  form.set("name", name);
  form.set("description", description);
  form.set("remove_background_noise", "true");
  form.append("files", sample, filename);
  const result = await call<{ voice_id: string }>("/voices/add", { method: "POST", body: form });
  return result.voice_id;
}

export async function deleteVoice(voiceId: string): Promise<void> {
  await call(`/voices/${encodeURIComponent(voiceId)}`, { method: "DELETE" });
}

export async function transcribe(sample: Blob, filename: string): Promise<string> {
  const form = new FormData();
  form.set("model_id", "scribe_v2");
  form.append("file", sample, filename);
  const result = await call<{ text: string }>("/speech-to-text", { method: "POST", body: form });
  return result.text ?? "";
}

export async function deleteAgent(agentId: string): Promise<void> {
  await call(`/convai/agents/${encodeURIComponent(agentId)}`, { method: "DELETE" });
}

// Short-lived, single-use token so a browser can open one ElevenLabs
// websocket without ever seeing the API key. Expires after 15 minutes.
export async function singleUseToken(type: "realtime_scribe" | "tts_websocket"): Promise<string> {
  const result = await call<{ token: string }>(`/single-use-token/${type}`, { method: "POST" });
  return result.token;
}

// Natural-mode voice change for one phrase of 16 kHz 16-bit mono PCM.
// Returns the streaming response so converted audio can be passed straight on.
export async function voiceChangeStream(voiceId: string, pcm: Uint8Array<ArrayBuffer>): Promise<Response> {
  const form = new FormData();
  form.set("model_id", "eleven_multilingual_sts_v2");
  form.set("file_format", "pcm_s16le_16");
  form.set("remove_background_noise", "false");
  form.append("audio", new Blob([pcm], { type: "application/octet-stream" }), "phrase.pcm");
  const response = await fetch(`${BASE}/voice-changer/${encodeURIComponent(voiceId)}/stream?output_format=pcm_16000`, {
    method: "POST",
    headers: { "xi-api-key": key() },
    body: form,
    cache: "no-store",
  });
  if (!response.ok || !response.body) {
    const body = await response.text().catch(() => "");
    throw new ElevenLabsError(`ElevenLabs voice changer failed: ${response.status} ${body.slice(0, 300)}`);
  }
  return response;
}
