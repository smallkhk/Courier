import assert from "node:assert/strict";
import { test } from "node:test";
import { extractScenarioPrompt, suggestName } from "../lib/extract.ts";

test("returns null when there is no complete code block", () => {
  assert.equal(extractScenarioPrompt("Who should the AI play?"), null);
  assert.equal(extractScenarioPrompt("Here it is:\n```\nROLE: buyer\n"), null);
});

test("returns the last code block's contents", () => {
  const reply = "First:\n```\nold\n```\nUpdated:\n```text\nROLE: A skeptical CFO\nSCENARIO: x\n```\nWant to test it, or change anything?";
  assert.equal(extractScenarioPrompt(reply), "ROLE: A skeptical CFO\nSCENARIO: x");
});

test("suggests a name from the ROLE line", () => {
  assert.equal(suggestName("ROLE: Dana, a skeptical CFO at a logistics firm\nGOALS: y"), "Dana");
  assert.equal(suggestName("ROLE: A tough hiring manager\n"), "A tough hiring manager");
  assert.equal(suggestName("no role here"), "New scenario");
});
