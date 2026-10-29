# CallMate Voice Changer (Windows)

A small Windows app around the website's voice changer. It adds:

- a tray icon, so it keeps working when you close the window
- **Ctrl+Shift+Space** to switch talking on and off from any app (with push-to-talk on)

## Download

GitHub Actions builds the installer on every push. Get it from the **voice-latest** release:
https://github.com/smallkhk/Courier/releases/download/voice-latest/VoiceChanger-Setup.exe

On first launch it asks for your website address, unless you set the repository variable `APP_URL` (GitHub → Settings → Secrets and variables → Actions → Variables) before the build.

The installer isn't code-signed, so Windows SmartScreen may say "Windows protected your PC". Click **More info → Run anyway**.

## Point it at your site

Or edit `config.json` and set `appUrl` to your website, for example `https://callmate.example.com`. Your site must use HTTPS (Namecheap AutoSSL is fine).

## Build the installer

On a Windows PC with Node.js 22 or newer:

```bash
cd desktop
npm install
npm run dist
```

The installer is written to `desktop/dist/`, for example `CallMate Voice Changer Setup 1.0.0.exe`. Put it on your site for users to download.

To try it without building, run `npm start`.

## Virtual microphone

Windows has no built-in virtual microphone, so users install the free **VB-Audio Virtual Cable** once: https://vb-audio.com/Cable/ (run the installer as administrator, then restart).

1. In the app, set **Send the new voice to** to **CABLE Input**.
2. In WhatsApp, Zoom, Discord, Telegram or OBS, set the microphone to **CABLE Output**.
