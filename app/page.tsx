import { redirect } from "next/navigation";
import { AuthForm } from "@/components/AuthForm";
import { currentUser } from "@/lib/auth.ts";
import { config } from "@/lib/config.ts";

export default async function Landing() {
  if (await currentUser()) redirect("/home");
  return (
    <main>
      <p className="eyebrow">{config.platformName}</p>
      <h1 className="hero">Let your AI voice take the call.</h1>
      <p className="muted lead">
        Call someone from your phone, put it on speaker, and press Start Call. Your AI talks for you, in your voice if
        you like, about exactly what you told it to, and remembers the last call next time.
      </p>
      <ol className="steps">
        <li>
          <strong>Set up once.</strong> Tell it about you, the people you call, and how you talk.
        </li>
        <li>
          <strong>Call.</strong> Add today&apos;s topics, call from your phone on speaker, press Start Call.
        </li>
        <li>
          <strong>Pick up later.</strong> Each call is summarised so the next one continues naturally.
        </li>
      </ol>
      <AuthForm />
    </main>
  );
}
