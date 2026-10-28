// Pulls the scenario prompt out of the setup assistant's reply: the contents
// of the last fenced code block. Returns null while no complete block exists.
export function extractScenarioPrompt(reply: string): string | null {
  const blocks = [...reply.matchAll(/```[^\n`]*\n([\s\S]*?)```/g)];
  const last = blocks.at(-1)?.[1]?.trim();
  return last ? last : null;
}

// Best-effort name for a new scenario, taken from the ROLE line.
export function suggestName(prompt: string): string {
  const role = prompt.match(/^ROLE:\s*(.+)$/im)?.[1]?.trim() ?? "";
  const short = role.split(/[.,;]/)[0].trim();
  return short ? short.slice(0, 60) : "New scenario";
}
