"use client";

import { useRouter } from "next/navigation";
import { useRef, useState } from "react";
import type { KnowledgeItem } from "@/lib/repo.ts";
import { api } from "./api";

const ICON: Record<KnowledgeItem["kind"], string> = { text: "📝", url: "🌐", file: "📄" };

export function KnowledgeManager({ items }: { items: KnowledgeItem[] }) {
  const router = useRouter();
  const [tab, setTab] = useState<"text" | "url" | "file">("text");
  const [name, setName] = useState("");
  const [content, setContent] = useState("");
  const [url, setUrl] = useState("");
  const [status, setStatus] = useState<{ ok?: string; error?: string }>({});
  const [busy, setBusy] = useState(false);
  const fileInput = useRef<HTMLInputElement>(null);

  async function add() {
    setBusy(true);
    setStatus({});
    try {
      if (tab === "file") {
        const file = fileInput.current?.files?.[0];
        if (!file) throw new Error("Choose a file");
        const form = new FormData();
        form.set("file", file);
        const response = await fetch("/api/knowledge", { method: "POST", body: form });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error((data as { error?: string }).error ?? "Upload failed");
        if (fileInput.current) fileInput.current.value = "";
      } else if (tab === "url") {
        await api("/api/knowledge", { method: "POST", body: JSON.stringify({ kind: "url", url, name }) });
        setUrl("");
      } else {
        await api("/api/knowledge", { method: "POST", body: JSON.stringify({ kind: "text", name, content }) });
        setContent("");
      }
      setName("");
      setStatus({ ok: "Added. Your AI will use it on the next call." });
      router.refresh();
    } catch (err) {
      setStatus({ error: (err as Error).message });
    } finally {
      setBusy(false);
    }
  }

  return (
    <section className="card stack">
      <div>
        <h2 style={{ marginBottom: 2 }}>What your AI knows</h2>
        <p className="small muted">
          Price lists, services, portfolio, FAQs, your website. Your AI uses this to answer detailed questions on calls.
        </p>
      </div>
      {items.length > 0 && (
        <ul className="timeline">
          {items.map((k) => (
            <li key={k.id} className="row spread" style={{ flexWrap: "nowrap" }}>
              <span style={{ minWidth: 0 }}>
                {ICON[k.kind]} <strong>{k.name}</strong>
                <div className="small muted" style={{ overflowWrap: "anywhere" }}>
                  {k.kind === "text" ? `${k.content.slice(0, 90)}${k.content.length > 90 ? "…" : ""}` : k.kind === "url" ? k.content : "Uploaded file"}
                </div>
              </span>
              <button
                className="danger"
                style={{ padding: "6px 12px", fontSize: "0.8rem" }}
                onClick={async () => {
                  if (!confirm(`Remove "${k.name}"?`)) return;
                  await api(`/api/knowledge/${k.id}`, { method: "DELETE" });
                  router.refresh();
                }}
              >
                Remove
              </button>
            </li>
          ))}
        </ul>
      )}
      <div className="net-toggle" style={{ gridTemplateColumns: "repeat(3, 1fr)" }}>
        {(
          [
            ["text", "📝 Note", "type it"],
            ["url", "🌐 Website", "a link"],
            ["file", "📄 File", "PDF, Word…"],
          ] as const
        ).map(([key, label, note]) => (
          <button key={key} type="button" className={tab === key ? "active" : ""} onClick={() => setTab(key)}>
            {label}
            <small>{note}</small>
          </button>
        ))}
      </div>
      {tab !== "file" && (
        <div>
          <label htmlFor="k-name">{tab === "text" ? "Title" : "Name (optional)"}</label>
          <input id="k-name" value={name} onChange={(e) => setName(e.target.value)} maxLength={80} placeholder={tab === "text" ? "e.g. My prices" : "e.g. My portfolio"} />
        </div>
      )}
      {tab === "text" && (
        <div>
          <label htmlFor="k-content">Note</label>
          <textarea
            id="k-content"
            rows={6}
            value={content}
            onChange={(e) => setContent(e.target.value)}
            placeholder={"e.g.\nLanding page: ₦150k, 1 week\nBusiness website (5 pages): ₦350k, 2 weeks\nFull platform with payments: from ₦1.2m\n50% upfront, 50% on delivery"}
          />
        </div>
      )}
      {tab === "url" && (
        <div>
          <label htmlFor="k-url">Website link</label>
          <input id="k-url" value={url} onChange={(e) => setUrl(e.target.value)} placeholder="https://vhonixcloud.com" />
        </div>
      )}
      {tab === "file" && (
        <div>
          <label htmlFor="k-file">File (PDF, Word, text, up to 10 MB)</label>
          <input id="k-file" ref={fileInput} type="file" accept=".pdf,.txt,.md,.docx,.html,.htm,.epub" />
        </div>
      )}
      {status.error && <p className="error small">{status.error}</p>}
      {status.ok && <p className="ok small">{status.ok}</p>}
      <div>
        <button onClick={add} disabled={busy}>
          {busy ? "Adding…" : "Add"}
        </button>
      </div>
    </section>
  );
}
