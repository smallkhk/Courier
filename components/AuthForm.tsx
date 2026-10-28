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
    <form className="card stack" onSubmit={submit}>
      <h2>{mode === "signup" ? "Create an account" : "Sign in"}</h2>
      <div>
        <label htmlFor="email">Email</label>
        <input id="email" name="email" type="email" autoComplete="email" required />
      </div>
      <div>
        <label htmlFor="password">Password</label>
        <input
          id="password"
          name="password"
          type="password"
          autoComplete={mode === "signup" ? "new-password" : "current-password"}
          minLength={mode === "signup" ? 10 : undefined}
          required
        />
      </div>
      {error && <p className="error">{error}</p>}
      <div className="row spread">
        <button type="submit" disabled={busy}>
          {mode === "signup" ? "Create account" : "Sign in"}
        </button>
        <button
          type="button"
          className="secondary"
          onClick={() => {
            setMode(mode === "signup" ? "login" : "signup");
            setError("");
          }}
        >
          {mode === "signup" ? "I already have an account" : "Create an account instead"}
        </button>
      </div>
    </form>
  );
}
