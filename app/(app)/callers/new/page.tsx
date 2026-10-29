import { CallerForm } from "@/components/CallerForm";
import { pageUser } from "@/lib/page-auth.ts";
import { voicesFor } from "@/lib/voices.ts";

export default async function NewCaller() {
  const user = await pageUser();
  const voices = voicesFor(user.accountId);
  return (
    <>
      <h1>Add a caller</h1>
      <p className="muted">Set this person up once. Next time you only add what today&apos;s call is about.</p>
      <section className="card">
        <CallerForm
          voices={voices}
          initial={{
            name: "",
            relationship: "",
            about: "",
            how_we_talk: "",
            location: "",
            timezone: "Africa/Lagos",
            language: "en",
            voice_id: voices[0].id,
            opening: "wait",
            custom_opening: "",
            kind: "personal",
            company: "",
            project: "",
            budget: "",
            stage: "",
          }}
        />
      </section>
    </>
  );
}
