"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { api } from "../api";

// Posts an admin action, optionally after a confirm prompt, then refreshes.
export function AdminButton({
  url,
  body,
  method = "POST",
  label,
  confirmText,
  className,
  onDone,
}: {
  url: string;
  body?: unknown;
  method?: string;
  label: string;
  confirmText?: string;
  className?: string;
  onDone?: () => void;
}) {
  const router = useRouter();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  return (
    <span style={{ display: "inline-flex", flexDirection: "column", gap: 4 }}>
      <button
        type="button"
        className={className}
        disabled={busy}
        onClick={async () => {
          if (confirmText && !confirm(confirmText)) return;
          setBusy(true);
          setError("");
          try {
            await api(url, { method, body: body === undefined ? undefined : JSON.stringify(body) });
            onDone?.();
            router.refresh();
          } catch (err) {
            setError((err as Error).message);
          } finally {
            setBusy(false);
          }
        }}
      >
        {busy ? "…" : label}
      </button>
      {error && <span className="error small">{error}</span>}
    </span>
  );
}
