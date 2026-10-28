# CallMate

Your AI voice takes your phone calls. Call someone from your phone, put it on speaker next to your laptop, and press **Start call**. The AI hears them through the laptop mic and answers through the speakers, speaking as you, in your cloned voice if you like. It only talks about what you told it to, and each call is summarised so the next one picks up where the last one ended.

- **Voice and AI:** ElevenLabs Agents, with **Claude Haiku 5.5** chosen inside ElevenLabs as the brain. ElevenLabs bills you for both, so there's no separate AI account or API credit.
- **Payments:** your own USDT gateway on **TRC20** and **BEP20**. Customers send USDT to your wallet, the app watches the chain and credits them automatically.
- **Stack:** Next.js 16, TypeScript, SQLite (`node:sqlite`, built into Node 22.13+).

## Run it

```bash
cp .env.example .env    # add ELEVENLABS_API_KEY and your USDT wallet addresses
npm install
npm run dev             # http://localhost:3000
npm run watch-payments  # in a second terminal: credits USDT payments in the background
```

Other commands:

```bash
npm test                                    # payments, billing, account isolation, call prompt, clone consent
npm run typecheck
npm run add-credit -- someone@example.com 5 # give an account $5 by hand
npm run approve-deposit -- <deposit-id>     # credit a top-up that is waiting for review
```

## How it works

**Pages:** Home (balance, callers, recent calls) · My profile · Callers (setup + live call) · Voices · Wallet.

1. **My profile:** your name, what people call you, where you live, about you, how you talk.
2. **Callers:** each person you call: who they are to you, about them, how you two talk, their location and time zone, the call language, the voice, and how the call opens. The AI can wait for them to say hello, greet first with good morning/afternoon/evening in their time zone, or use your own opening line.
3. **Calling:** write today's topics, then press Start call. The server builds the AI's instructions from your profile, the caller, today's topics and the last 3 call summaries, and sets them on your ElevenLabs agent. The browser only gets a short-lived link, so nobody can change the instructions from the browser.
4. **Live screen:** timer, live transcript, **Wrap up** (the AI finishes its point, says goodbye, then the call ends), Mute AI's ears, End now.
5. **After the call:** the app bills the call by the second using the length ElevenLabs reports, and saves the transcript and ElevenLabs' summary for next time.

### Voices

- 6 built-in voices.
- **Voice library:** search thousands of ElevenLabs voices by accent, gender or language, play samples, and save the ones you like.
- **Clone your voice:** the customer reads a consent statement with 4 random code words, then keeps talking for at least a minute. The recording is transcribed, and the clone is only made if the code words are in it. That makes it hard to clone someone else's voice from an old recording. Clones are private to the account, with a limit of 1 per account (`MAX_CLONES_PER_ACCOUNT`).

### USDT top-ups

1. The customer picks a network and an amount, e.g. 10 USDT.
2. The app asks for a **unique exact amount**, e.g. **10.37 USDT**, valid for 1 hour, and shows your wallet address with a QR code. The cents are what tie the payment to that customer.
3. The watcher (`npm run watch-payments`, plus the wallet page while it's open) reads incoming USDT transfers to your wallets every ~20 seconds. TRC20 uses TronGrid; BEP20 uses a BNB Chain node. A confirmed transfer with the exact amount credits that customer.
4. Customers can also paste their transaction hash to check straight away.
5. Every transaction hash is credited only once.
6. If the amount received is different (usually an exchange took its fee out), the top-up goes to **review**. Confirm with the customer that it's theirs, then run `npm run approve-deposit -- <id>`.

## Admin panel (`/admin`)

1. Put your email in `ADMIN_EMAILS` in `.env`.
2. Sign up with that email. An **Admin** button then appears in the header.

Anyone who isn't an admin gets a normal "page not found" on `/admin` and on every admin API.

- **Overview:** users, USDT received, call revenue, estimated profit after ElevenLabs cost, unused balances, top-ups waiting or needing review, and daily revenue and deposit charts.
- **Users:** search and filter (paying, blocked, admins). Each user has a full page:
  - Add or remove credit, with a reason they see in their history.
  - Block (signs them out and stops sign-in, showing your reason) or unblock.
  - Make admin or remove admin.
  - Set a new password, or sign them out everywhere.
  - Delete the account. This also deletes their cloned voice and agent on ElevenLabs.
  - See their profile, callers, voices, top-ups, calls with transcripts, and full balance history.
- **Payments:** every top-up, with filters. Each transaction hash links to Tronscan or BscScan. Actions:
  - **Approve** a top-up under review.
  - **Mark paid**, entering the amount received and optionally the hash, which can't then be reused.
  - **Reject**.
- **Calls:** every call on the site, with topics, summary and full transcript.
- **Voices:** every saved and cloned voice. Removing a clone deletes it from ElevenLabs too.
- **Settings**, live as soon as you save:
  - Site name and an announcement banner shown to all users.
  - Sign-ups on/off, calls on/off (maintenance mode), top-ups on/off.
  - Price per minute, your cost per minute (for the profit figure), free sign-up credit, minimum top-up, longest call.
  - USDT TRC20 and BEP20 wallet addresses.
  - AI model, voice model, and cloned voices per account (0 turns cloning off).
- **Activity log:** every admin action, with who did it, to whom and when.

## What the AI won't do

Built into every call's instructions:
- It speaks as the account owner and stays within the topics and normal small talk.
- It doesn't make up facts about you, and doesn't make new promises for you.
- It never asks for or gives out passwords, PINs, one-time codes, card numbers or wallet keys, and never gives out bank account numbers or wallet addresses.
- If the other person sincerely asks whether they're talking to an AI, it doesn't deny it. It says you set up a voice assistant, and offers to have you follow up.

## Before going live

- **ElevenLabs plan:** check how many voices your plan can hold. Every saved library voice and every clone takes a slot.
- **BEP20 detection:** the default free node (`bsc-rpc.publicnode.com`) works but rate-limits. For production, set `BSC_RPC_URL` to your own provider node, e.g. NodeReal, QuickNode or Ankr. Also set `TRONGRID_API_KEY` (free at trongrid.io) for higher TRON limits.
- **Run the watcher** as a service (pm2, systemd) next to the web app.
- **Hosting:** SQLite needs a persistent disk. For several servers, move `lib/db.ts` to Postgres.
- **Rate limiting** on login and sign-up.
- **Laws and terms:** some places have rules about AI voices on calls, and the other person's voice is processed by ElevenLabs. Cover this in your terms of service.
