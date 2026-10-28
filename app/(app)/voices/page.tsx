import { VoicesClient } from "@/components/VoicesClient";
import { config } from "@/lib/config.ts";
import { pageUser } from "@/lib/page-auth.ts";
import { countClones, listSavedVoices } from "@/lib/repo.ts";
import { DEFAULT_VOICES } from "@/lib/voices.ts";

export default async function VoicesPage() {
  const user = await pageUser();
  return (
    <>
      <h1>Voices</h1>
      <p className="muted">How your AI sounds on calls: a built-in voice, one from the library, or your own.</p>
      <VoicesClient
        saved={listSavedVoices(user.accountId)}
        defaults={DEFAULT_VOICES}
        canClone={countClones(user.accountId) < config.maxClonesPerAccount}
      />
    </>
  );
}
