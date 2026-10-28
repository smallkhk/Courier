import { redirect } from "next/navigation";
import { AuthForm } from "@/components/AuthForm";
import { currentUser } from "@/lib/auth.ts";
import { config } from "@/lib/config.ts";

export default async function Home() {
  if (await currentUser()) redirect("/dashboard");
  return (
    <main>
      <h1>{config.platformName}</h1>
      <p className="muted">
        Describe a conversation you want to practice. We turn it into a voice AI character you can talk to out loud,
        as many times as you like.
      </p>
      <AuthForm />
    </main>
  );
}
