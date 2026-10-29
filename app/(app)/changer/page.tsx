import { VoiceChanger } from "@/components/VoiceChanger";
import { config } from "@/lib/config.ts";
import { pageUser } from "@/lib/page-auth.ts";
import { getCredit } from "@/lib/repo.ts";
import { voicesFor } from "@/lib/voices.ts";

export default async function ChangerPage() {
  const user = await pageUser();
  return (
    <>
      <h1>Voice changer</h1>
      <p className="muted">Talk into your mic and come out in another voice, live. Send it into any app as a virtual microphone.</p>
      <VoiceChanger
        voices={voicesFor(user.accountId)}
        initialCredit={getCredit(user.accountId)}
        prices={{ fast: config.changerFastPerMinuteMicros, natural: config.changerNaturalPerMinuteMicros }}
        enabled={config.changerEnabled}
      />
    </>
  );
}
