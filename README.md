# Voice Practice

A platform where customers describe a conversation they want to practice (a sales call, an interview, a tough negotiation), a setup assistant turns that into a voice-agent prompt, and they practice it out loud with a live voice AI.

- **Setup assistant:** Claude (`claude-opus-5-5`) chats with the customer, asks for whatever is missing (role, goal, facts, difficulty), and outputs the scenario prompt in a code block.
- **Voice:** each saved scenario becomes its own ElevenLabs agent. The browser gets a short-lived signed URL only, so it can't change the prompt or voice.
- **Stack:** Next.js 16, TypeScript, SQLite (`node:sqlite`, built into Node 22.13+).

## Run it

```bash
cp .env.example .env    # add ANTHROPIC_API_KEY and ELEVENLABS_API_KEY
npm install
npm run dev             # http://localhost:3000
```

Without `ANTHROPIC_API_KEY`, saves are rejected, because the safety check fails closed. Without `ELEVENLABS_API_KEY`, scenarios save but calls can't start.

```bash
npm test                # unit tests: billing, double-charge guard, tenant isolation, prompt extraction
npm run typecheck
npm run add-credit -- someone@example.com 10   # give an account $10 of credit
```

## How the resale requirements are met

| Requirement | Where |
|---|---|
| **Usage metering:** charge seconds of voice and LLM calls against a credit balance with a margin | `lib/billing.ts`, `lib/claude.ts`. Voice is billed per second at `PRICE_PER_MINUTE_USD` (default $0.05/min, which is $0.50 per 10 min against roughly $0.18 of cost). Claude tokens are billed at list price × `LLM_MARKUP`. Every change is written to the `usage_events` ledger. |
| **Billing can't be skipped** | Call length comes from ElevenLabs, not the browser. `settleScenario` lists every finished conversation on the scenario's agent and bills any that aren't recorded yet. A unique `conversation_id` stops a call from being billed twice. |
| **Tenant isolation:** scenarios and transcripts stored under the account and every query filtered by it | `lib/repo.ts` is the only data-access layer, and every query filters on `account_id`. Covered in `tests/billing.test.ts`. |
| **Voice choice:** fixed list, no cloning without consent | `lib/voices.ts`. Six ElevenLabs premade voices by default, overridable with `VOICES_JSON`. There is no voice-cloning feature. |
| **Abuse guard on save, not just in the first message** | `lib/scenarios.ts` runs `checkScenario` (Claude, structured output) on every create and edit **before** the prompt reaches the voice agent. It fails closed. The setup assistant prompt also carries the boundaries. |
| **Fixed rules on every scenario** | `lib/prompts.ts`. The setup assistant writes them into the prompt, and `buildAgentPrompt` appends them again on the server, so deleting them from the saved text has no effect. |

## Before opening to customers

- **Payments:** credit is added with `npm run add-credit` for now. Connect Stripe (or similar) to `applyCredit`.
- **Concurrent calls:** a call starts if the account has at least one minute of credit, and each call is capped at `MAX_CALL_SECONDS`. Several calls started at once can push the balance below zero by up to one call each. Add a per-account limit on concurrent calls if that matters.
- **Rate limiting** on login, signup and the setup chat.
- **ElevenLabs:** check your plan's limit on the number of agents, since each scenario creates one.
- **Hosting:** SQLite needs a persistent disk. For several app servers, move `lib/db.ts` to Postgres. The queries are plain SQL.
