const GRADIENTS = [
  "linear-gradient(135deg,#8b5cf6,#ec4899)",
  "linear-gradient(135deg,#06b6d4,#8b5cf6)",
  "linear-gradient(135deg,#f59e0b,#ec4899)",
  "linear-gradient(135deg,#10b981,#06b6d4)",
  "linear-gradient(135deg,#f43f5e,#f59e0b)",
  "linear-gradient(135deg,#6366f1,#22d3ee)",
];

function hash(text: string): number {
  let h = 0;
  for (const ch of text) h = (h * 31 + ch.charCodeAt(0)) >>> 0;
  return h;
}

export function Avatar({ name, size }: { name: string; size?: "lg" }) {
  const initials = name
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase() ?? "")
    .join("") || "?";
  return (
    <span className={`avatar ${size ?? ""}`} style={{ background: GRADIENTS[hash(name) % GRADIENTS.length] }}>
      {initials}
    </span>
  );
}
