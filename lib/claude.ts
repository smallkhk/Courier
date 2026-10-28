import Anthropic from "@anthropic-ai/sdk";
import { betaZodOutputFormat } from "@anthropic-ai/sdk/helpers/beta/zod";
import { z } from "zod";
import { config } from "./config.ts";
import { SCENARIO_CHECK_PROMPT, setupAssistantPrompt } from "./prompts.ts";
import { applyCredit } from "./repo.ts";

let client: Anthropic | undefined;

function getClient(): Anthropic {
  client ??= new Anthropic();
  return client;
}

// On a policy decline the API re-runs the request on Anthropic's recommended
// fallback model inside the same call instead of returning a refusal.
const FALLBACK = {
  betas: ["server-side-fallback-2026-07-01"],
  fallbacks: "default" as const,
};

export type ChatTurn = { role: "user" | "assistant"; content: string };

export function llmChargeMicros(usage: { input_tokens: number; output_tokens: number }): number {
  const cost =
    usage.input_tokens * config.claudeInputMicrosPerToken +
    usage.output_tokens * config.claudeOutputMicrosPerToken;
  return Math.ceil(cost * config.llmMarkup);
}

function chargeUsage(
  accountId: string,
  detail: string,
  usage: { input_tokens: number; output_tokens: number },
): void {
  const tokens = usage.input_tokens + usage.output_tokens;
  applyCredit(accountId, -llmChargeMicros(usage), "llm", detail, tokens);
}

// Streams the setup assistant's reply as plain text and charges the account
// for the tokens once the reply is complete.
export function streamSetupReply(accountId: string, turns: ChatTurn[]): ReadableStream<Uint8Array> {
  const encoder = new TextEncoder();
  return new ReadableStream({
    async start(controller) {
      try {
        const stream = getClient().beta.messages.stream({
          ...FALLBACK,
          model: config.claudeModel,
          max_tokens: 8000,
          output_config: { effort: "low" },
          system: [
            {
              type: "text",
              text: setupAssistantPrompt(config.platformName),
              cache_control: { type: "ephemeral" },
            },
          ],
          messages: turns,
        });
        stream.on("text", (delta) => controller.enqueue(encoder.encode(delta)));
        const message = await stream.finalMessage();
        chargeUsage(accountId, "Setup assistant", message.usage);
        if (message.stop_reason === "refusal") {
          controller.enqueue(
            encoder.encode("\n\nSorry, I can't help with that scenario. Want to try a different one?"),
          );
        }
        controller.close();
      } catch (err) {
        console.error("setup assistant failed", err);
        controller.enqueue(encoder.encode("\n\nSomething went wrong on our side. Please try again."));
        controller.close();
      }
    },
  });
}

const ScenarioCheck = z.object({
  allowed: z.boolean(),
  reason: z.string(),
});

export type ScenarioCheckResult = z.infer<typeof ScenarioCheck>;

// The boundary check run on every save. It fails closed: if Claude can't be
// reached or the answer can't be parsed, the save is rejected.
export async function checkScenario(
  accountId: string,
  scenario: { name: string; prompt: string; firstMessage: string },
): Promise<ScenarioCheckResult> {
  try {
    const response = await getClient().beta.messages.parse({
      ...FALLBACK,
      model: config.claudeModel,
      max_tokens: 4000,
      output_config: { effort: "low", format: betaZodOutputFormat(ScenarioCheck) },
      system: SCENARIO_CHECK_PROMPT,
      messages: [
        {
          role: "user",
          content: `<scenario_name>${scenario.name}</scenario_name>\n<opening_line>${scenario.firstMessage}</opening_line>\n<scenario>\n${scenario.prompt}\n</scenario>`,
        },
      ],
    });
    chargeUsage(accountId, "Scenario safety check", response.usage);
    if (response.stop_reason === "refusal") {
      return { allowed: false, reason: "This scenario isn't allowed on the platform." };
    }
    if (!response.parsed_output) {
      return { allowed: false, reason: "We couldn't check this scenario. Please try again." };
    }
    return response.parsed_output;
  } catch (err) {
    console.error("scenario check failed", err);
    return { allowed: false, reason: "We couldn't check this scenario. Please try again." };
  }
}
