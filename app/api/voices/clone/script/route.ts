import { HttpError, requireUser } from "@/lib/auth.ts";
import { issueConsentScript } from "@/lib/clone-consent.ts";
import { config } from "@/lib/config.ts";
import { json, route } from "@/lib/http.ts";
import { countClones, getProfile } from "@/lib/repo.ts";

export const POST = route(async (request: Request) => {
  const user = await requireUser(request);
  const profile = getProfile(user.accountId);
  if (!profile.full_name.trim()) throw new HttpError(409, "Add your name on the My Profile page first.");
  if (countClones(user.accountId) >= config.maxClonesPerAccount) {
    throw new HttpError(409, "You've reached your cloned voice limit. Delete your clone to make a new one.");
  }
  return json({ script: issueConsentScript(user.accountId, profile.full_name.trim(), config.platformName) });
});
