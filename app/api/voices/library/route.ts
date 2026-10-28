import { requireUser } from "@/lib/auth.ts";
import { searchLibrary } from "@/lib/elevenlabs.ts";
import { json, route } from "@/lib/http.ts";

export const GET = route(async (request: Request) => {
  await requireUser();
  const params = new URL(request.url).searchParams;
  const pick = (name: string) => params.get(name)?.slice(0, 60) || undefined;
  const result = await searchLibrary({
    search: pick("search"),
    gender: pick("gender"),
    accent: pick("accent"),
    language: pick("language"),
    page: Math.max(0, Math.min(50, Number(params.get("page")) || 0)),
  });
  return json(result);
});
