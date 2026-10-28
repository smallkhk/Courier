import { z } from "zod";
import { logAdmin } from "@/lib/admin.ts";
import { HttpError, requireAdmin } from "@/lib/auth.ts";
import { transaction } from "@/lib/db.ts";
import { json, readJson, route } from "@/lib/http.ts";
import { saveSetting, SETTINGS, validateSetting } from "@/lib/settings.ts";

const Body = z.object({ values: z.record(z.string(), z.string().max(1000)) });

export const PUT = route(async (request: Request) => {
  const admin = await requireAdmin(request);
  const { values } = await readJson(request, Body);
  const known = new Set(SETTINGS.map((s) => s.key));
  // Validate everything first so a bad value saves nothing.
  for (const [key, value] of Object.entries(values)) {
    if (!known.has(key)) throw new HttpError(400, `Unknown setting ${key}`);
    try {
      validateSetting(key, value);
    } catch (err) {
      throw new HttpError(400, (err as Error).message);
    }
  }
  const changed: string[] = [];
  transaction(() => {
    for (const [key, value] of Object.entries(values)) {
      const { before, after } = saveSetting(key, value);
      if (before !== after) changed.push(`${key}: "${before}" → "${after}"`);
    }
  });
  if (changed.length) logAdmin(admin.email, "settings.update", "site", changed.join("; "));
  return json({ ok: true, changed: changed.length });
});
