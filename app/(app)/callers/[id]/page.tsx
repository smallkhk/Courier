import { notFound } from "next/navigation";
import { Avatar } from "@/components/Avatar";
import { CallerForm } from "@/components/CallerForm";
import { CallPanel } from "@/components/CallPanel";
import { languageName } from "@/lib/languages.ts";
import { pageUser } from "@/lib/page-auth.ts";
import { getCaller, getCredit, listCalls } from "@/lib/repo.ts";
import { voicesFor } from "@/lib/voices.ts";

export default async function CallerPage({ params }: { params: Promise<{ id: string }> }) {
  const user = await pageUser();
  const { id } = await params;
  const caller = getCaller(user.accountId, id);
  if (!caller) notFound();
  const voices = voicesFor(user.accountId);
  const { id: _id, created_at: _c, updated_at: _u, ...fields } = caller;

  return (
    <>
      <div className="caller-head">
        <Avatar name={caller.name} size="lg" />
        <div>
          <h1>{caller.name}</h1>
          <div className="row" style={{ gap: 6 }}>
            {caller.relationship && <span className="pill">{caller.relationship}</span>}
            {caller.location && <span className="pill">📍 {caller.location}</span>}
            <span className="pill">🗣 {languageName(caller.language)}</span>
          </div>
        </div>
      </div>
      <CallPanel
        key={caller.id}
        callerId={caller.id}
        callerName={caller.name}
        defaultVoiceId={voices.some((v) => v.id === caller.voice_id) ? caller.voice_id : voices[0].id}
        defaultLanguage={caller.language}
        voices={voices}
        initialCalls={listCalls(user.accountId, { callerId: caller.id })}
        initialCredit={getCredit(user.accountId)}
      />
      <section className="card">
        <details>
          <summary>
            <span className="chev">›</span> <strong>Edit {caller.name}&apos;s details</strong>
          </summary>
          <div style={{ marginTop: 8 }}>
            <CallerForm callerId={caller.id} initial={fields} voices={voices} />
          </div>
        </details>
      </section>
    </>
  );
}
