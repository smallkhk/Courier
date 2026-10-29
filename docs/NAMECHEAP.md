# Putting CallMate on Namecheap (Stellar shared hosting)

## The lazy way (one paste)

1. Open cPanel → **Terminal**.
2. Paste this line and press Enter:

```
bash <(curl -fsSL https://raw.githubusercontent.com/smallkhk/Courier/claude/voice-practice/scripts/install-cpanel.sh)
```

3. Answer the questions: domain, your admin email, ElevenLabs key and wallet addresses.

It creates the Node.js app in a folder called **`voice`**, downloads the latest build from GitHub, saves your settings, adds the payment Cron Job and starts the site.

To update later, paste the same line again. Your settings and data are kept.

The manual steps below do the same thing by hand.

Everything runs on your Namecheap hosting, with no extra server:

- **AI calls:** the browser talks straight to ElevenLabs.
- **Voice changer, Fast mode:** the browser talks straight to ElevenLabs using one-time passes.
- **Voice changer, Natural mode:** each phrase is a short upload to your site.
- **USDT payments:** checked once a minute by a Cron Job.

You need:
- the `voice.zip` file (from the voice-latest GitHub release, or `npm run package:namecheap`)
- your ElevenLabs API key
- your USDT TRC20 and/or BEP20 wallet addresses

---

## 1. Turn on HTTPS

cPanel → **SSL/TLS Status** → make sure your domain has a valid certificate (AutoSSL). The microphone and voice features only work on `https://`.

## 2. Create the Node.js app

cPanel → **Software → Setup Node.js App** → **Create Application**:

| Field | Value |
|---|---|
| Node.js version | **24** (or 22 if 24 isn't listed) |
| Application mode | Production |
| Application root | `voice` (a folder **outside** public_html) |
| Application URL | your domain, e.g. `yourdomain.com` |
| Application startup file | `app.cjs` |

Click **Create**, then **Stop App** for now.

At the top of the app page cPanel shows a command like
`source /home/USERNAME/nodevenv/voice/24/bin/activate && cd /home/USERNAME/voice`.
Note the **nodevenv path**. You need it for the Cron Job.

## 3. Upload the app

cPanel → **File Manager** → open the `voice` folder:

1. Delete anything the setup created in it (for example a sample `app.js`), but keep the folder.
2. **Upload** `voice.zip`.
3. Right-click it → **Extract** into `/home/USERNAME/voice/`.
4. Check that `voice/app.cjs`, `voice/server.js` and `voice/.next` are there. Turn on **Settings → Show Hidden Files** to see `.next` and `.env.example`.

Don't click "Run NPM Install". The app already contains everything it needs.

## 4. Add your settings

In `voice`, copy `.env.example` to **`.env`**, then edit `.env`:

```
ADMIN_EMAILS=you@example.com
ELEVENLABS_API_KEY=sk_...
USDT_TRC20_ADDRESS=T...
USDT_BEP20_ADDRESS=0x...
DATABASE_PATH=./data/app.db
```

Most other settings (prices, switches, wallets) can be changed later on the admin **Settings** page.

## 5. Start it

Back in **Setup Node.js App** → **Start App** (or Restart). Open your domain:

1. Sign up with the email you put in `ADMIN_EMAILS`.
2. An **Admin** button appears in the header.

If you see an error page, check `voice/stderr.log` in File Manager.

## 6. Payment checker (Cron Job)

cPanel → **Cron Jobs** → **Add New Cron Job** → Common Settings: **Once Per Minute**. Command (use your username and nodevenv path from step 2):

```
cd /home/USERNAME/voice && /home/USERNAME/nodevenv/voice/24/bin/node --env-file=.env --experimental-strip-types scripts/scan-payments-once.ts >> payments.log 2>&1
```

USDT payments are now credited automatically within about a minute.

## 7. Windows voice changer app

Download `VoiceChanger-Setup.exe` from the voice-latest GitHub release. On first launch it asks for your website address (see `desktop/README.md`).

---

## Updating to a new version

1. **Stop App**.
2. Upload the new `voice.zip` and extract it over the old folder. Your `.env` and `data` folder aren't in the zip, so they're kept.
3. **Start App**.

## Backups

All your data (users, balances, calls, payments) is in `voice/data/`. Download that folder now and then. Namecheap's own backups include it too.

## Admin commands (optional, via cPanel Terminal)

```
source /home/USERNAME/nodevenv/voice/24/bin/activate && cd /home/USERNAME/voice
node --env-file=.env --experimental-strip-types scripts/add-credit.ts someone@example.com 5
```

Everything these do can also be done from the admin panel.
