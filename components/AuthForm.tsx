"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { api } from "./api";

export function AuthForm() {
  const router = useRouter();
  const [mode, setMode] = useState<"login" | "signup">("signup");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  async function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    setBusy(true);
    setError("");
    try {
      await api(`/api/auth/${mode}`, { method: "POST", body: JSON.stringify(Object.fromEntries(form)) });
      router.push(mode === "signup" ? "/profile" : "/home");
      router.refresh();
    } catch (err) {
      setError((err as Error).message);
      setBusy(false);
    }
  }

  return (
    <form className="card card-glow stack" onSubmit={submit}>
      <div>
        <h2 style={{ fontSize: "1.4rem", marginBottom: 2 }}>{mode === "signup" ? "Get started" : "Welcome back"}</h2>
        <p className="muted small">{mode === "signup" ? "Create your account in seconds." : "Sign in to your calls."}</p>
      </div>
      <div>
        <label htmlFor="email">Email</label>
        <input id="email" name="email" type="email" autoComplete="email" placeholder="you@example.com" required />
      </div>
      <div>
        <label htmlFor="password">Password</label>
        <input
          id="password"
          name="password"
          type="password"
          autoComplete={mode === "signup" ? "new-password" : "current-password"}
          minLength={mode === "signup" ? 10 : undefined}
          placeholder={mode === "signup" ? "At least 10 characters" : ""}
          required
        />
      </div>
      {error && <p className="error small">{error}</p>}
      <button type="submit" disabled={busy} style={{ width: "100%" }}>
        {busy ? "One moment…" : mode === "signup" ? "Create account" : "Sign in"}
      </button>
      <p className="small muted" style={{ textAlign: "center" }}>
        {mode === "signup" ? "Already have an account? " : "New here? "}
        <a
          href="#"
          onClick={(e) => {
            e.preventDefault();
            setMode(mode === "signup" ? "login" : "signup");
            setError("");
          }}
        >
          {mode === "signup" ? "Sign in" : "Create an account"}
        </a>
      </p>
    </form>
  );
}
