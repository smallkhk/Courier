import { z } from "zod";
import { HttpError, requireUser } from "@/lib/auth.ts";
import { knowledgeFromFile, knowledgeFromUrl } from "@/lib/elevenlabs.ts";
import { json, readJson, route } from "@/lib/http.ts";
import { insertKnowledge, listKnowledge } from "@/lib/repo.ts";

const MAX_ITEMS = 25;
const MAX_NOTES_CHARS = 20_000; // all notes together; they go into every call's instructions
const MAX_FILE_BYTES = 10_000_000;
const FILE_TYPES = /\.(pdf|txt|md|docx|html?|epub)$/i;

const Note = z.object({
  kind: z.literal("text"),
  name: z.string().trim().min(1, "Give the note a title").max(80),
  content: z.string().trim().min(1, "Write something").max(MAX_NOTES_CHARS),
});
const Url = z.object({
  kind: z.literal("url"),
  url: z.string().trim().url("Enter a full link starting with https://"),
  name: z.string().trim().max(80).default(""),
});

export const GET = route(async () => {
  const user = await requireUser();
  return json({ items: listKnowledge(user.accountId) });
});

// Adds a note (kept here), a website or a file (stored on ElevenLabs).
export const POST = route(async (request: Request) => {
  const user = await requireUser(request);
  const existing = listKnowledge(user.accountId);
  if (existing.length >= MAX_ITEMS) throw new HttpError(400, `You can keep up to ${MAX_ITEMS} items. Remove one first.`);

  if ((request.headers.get("content-type") ?? "").includes("multipart/form-data")) {
    const form = await request.formData();
    const file = form.get("file");
    if (!(file instanceof File) || file.size === 0) throw new HttpError(400, "Choose a file");
    if (file.size > MAX_FILE_BYTES) throw new HttpError(400, "Files can be up to 10 MB");
    if (!FILE_TYPES.test(file.name)) throw new HttpError(400, "Use a PDF, Word (.docx), text, Markdown or HTML file");
    const name = file.name.slice(0, 80);
    const id = await knowledgeFromFile(file, file.name, name);
    insertKnowledge(user.accountId, { kind: "file", name, content: "", elDocId: id });
    return json({ ok: true }, 201);
  }

  const body = await readJson(request, z.discriminatedUnion("kind", [Note, Url]));
  if (body.kind === "text") {
    const used = existing.filter((k) => k.kind === "text").reduce((n, k) => n + k.content.length, 0);
    if (used + body.content.length > MAX_NOTES_CHARS) {
      throw new HttpError(400, "Your notes are too long in total. Shorten some, or upload a file instead.");
    }
    insertKnowledge(user.accountId, { kind: "text", name: body.name, content: body.content, elDocId: null });
  } else {
    const name = body.name || new URL(body.url).hostname;
    const id = await knowledgeFromUrl(body.url, name);
    insertKnowledge(user.accountId, { kind: "url", name, content: body.url, elDocId: id });
  }
  return json({ ok: true }, 201);
});
