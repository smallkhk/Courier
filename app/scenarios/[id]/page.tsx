import Link from "next/link";
import { notFound } from "next/navigation";
import { ScenarioView } from "@/components/ScenarioView";
import { pageUser } from "@/lib/page-auth.ts";
import { getCredit, getScenario, listSessions } from "@/lib/repo.ts";
import { publicScenario } from "@/lib/scenarios.ts";
import { VOICES } from "@/lib/voices.ts";

export const dynamic = "force-dynamic";

export default async function ScenarioPage({ params }: { params: Promise<{ id: string }> }) {
  const user = await pageUser();
  const { id } = await params;
  const scenario = getScenario(user.accountId, id);
  if (!scenario) notFound();
  return (
    <main>
      <Link href="/dashboard" className="small">
        ← Back to scenarios
      </Link>
      <h1>{scenario.name}</h1>
      <ScenarioView
        key={scenario.id}
        initialScenario={publicScenario(scenario)}
        initialSessions={listSessions(user.accountId, id)}
        initialCredit={getCredit(user.accountId)}
        voices={VOICES}
      />
    </main>
  );
}
