import { HttpError, requireUser } from "@/lib/auth.ts";
import { deleteKnowledgeDoc } from "@/lib/elevenlabs.ts";
import { json, route } from "@/lib/http.ts";
import { deleteKnowledge, getKnowledge } from "@/lib/repo.ts";

type Ctx = { params: Promise<{ id: string }> };

export const DELETE = route(async (request: Request, { params }: Ctx) => {
  const user = await requireUser(request);
  const { id } = await params;
  const item = getKnowledge(user.accountId, id);
  if (!item) throw new HttpError(404, "Not found");
  deleteKnowledge(user.accountId, id);
  // The next call stops using it either way; ElevenLabs may refuse to delete
  // a document an agent still lists, so a failure here isn't fatal.
  if (item.el_doc_id) await deleteKnowledgeDoc(item.el_doc_id).catch((err) => console.warn("knowledge delete failed", err));
  return json({ ok: true });
});
