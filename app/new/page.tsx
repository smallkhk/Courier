import Link from "next/link";
import { SetupChat } from "@/components/SetupChat";
import { pageUser } from "@/lib/page-auth.ts";
import { VOICES } from "@/lib/voices.ts";

export const dynamic = "force-dynamic";

export default async function NewScenario() {
  await pageUser();
  return (
    <main>
      <Link href="/dashboard" className="small">
        ← Back to scenarios
      </Link>
      <h1>New scenario</h1>
      <p className="muted">Describe the conversation in your own words. The assistant asks for anything missing.</p>
      <SetupChat voices={VOICES} />
    </main>
  );
}
