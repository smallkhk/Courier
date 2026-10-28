import { redirect } from "next/navigation";
import { AuthForm } from "@/components/AuthForm";
import { LogoMark, SparkIcon } from "@/components/Icons";
import { Orb } from "@/components/Orb";
import { currentUser } from "@/lib/auth.ts";
import { config } from "@/lib/config.ts";

const FEATURES = [
  { title: "Set up once", body: "Tell it about you, the people you call, and how you talk. It remembers." },
  { title: "Call on speaker", body: "Dial from your phone, put it on speaker, tap Start call. Your AI takes it from there." },
  { title: "Sounds like you", body: "Pick from thousands of realistic voices, or clone your own in a minute." },
  { title: "Picks up where you left off", body: "Every call is summarised, so the next one continues naturally." },
];

export default async function Landing() {
  if (await currentUser()) redirect("/home");
  return (
    <main>
      <div className="brand" style={{ marginBottom: 10 }}>
        <LogoMark />
        {config.platformName}
      </div>
      <section className="landing">
        <div>
          <span className="eyebrow">
            <SparkIcon size={14} /> Your voice, on autopilot
          </span>
          <h1 className="hero">
            Let your AI <span className="grad-text">take the call.</span>
          </h1>
          <p className="lead">
            It greets them, talks like you, sticks to what you want discussed, and wraps up warmly when you&apos;re
            done. You just dial.
          </p>
          <div className="hero-visual">
            <Orb />
          </div>
        </div>
        <AuthForm />
      </section>
      <section className="features">
        {FEATURES.map((f, i) => (
          <div key={f.title} className="feature">
            <span className="num">0{i + 1}</span>
            <h3>{f.title}</h3>
            <p className="muted small">{f.body}</p>
          </div>
        ))}
      </section>
    </main>
  );
}
