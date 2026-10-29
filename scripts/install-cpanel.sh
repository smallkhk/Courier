#!/bin/bash
# One-paste installer / updater for cPanel hosting with CloudLinux
# "Setup Node.js App" (e.g. Namecheap Stellar). Run in cPanel → Terminal:
#
#   bash <(curl -fsSL https://raw.githubusercontent.com/smallkhk/Courier/claude/voice-practice/scripts/install-cpanel.sh)
#
# First run: asks a few questions, creates the Node.js app in ~/voice,
# writes .env, adds the payment Cron Job and starts the site.
# Later runs: download the newest version and restart, keeping .env and data.
set -euo pipefail

APP_NAME="voice"
APP_DIR="$HOME/$APP_NAME"
ZIP_URL="${VOICE_ZIP_URL:-https://github.com/smallkhk/Courier/releases/download/voice-latest/voice.zip}"
WORK="$HOME/.voice-install"

say() { printf '\n\033[1;35m▶ %s\033[0m\n' "$*"; }
ok() { printf '  \033[1;32m✓\033[0m %s\n' "$*"; }
die() { printf '\n\033[1;31m✗ %s\033[0m\n' "$*" >&2; exit 1; }
ask() { # ask VAR "Question" "default"; skipped if VAR is already set
  [ -n "${!1:-}" ] && return 0
  local answer
  read -r -p "  $2${3:+ [$3]}: " answer < /dev/tty || true
  printf -v "$1" '%s' "${answer:-${3:-}}"
}

command -v cloudlinux-selector >/dev/null || die "This host has no 'Setup Node.js App' (cloudlinux-selector). Ask your host to enable Node.js."
command -v curl >/dev/null || die "curl is missing."

# ------------------------------------------------------------- settings
FIRST_RUN=0
[ -f "$APP_DIR/.env" ] || FIRST_RUN=1
DOMAIN="${DOMAIN:-}" ADMIN_EMAIL="${ADMIN_EMAIL:-}" EL_KEY="${EL_KEY:-}" TRC20="${TRC20:-}" BEP20="${BEP20:-}"

MAIN_DOMAIN="$(uapi --output=json DomainInfo list_domains 2>/dev/null | sed -n 's/.*"main_domain":"\([^"]*\)".*/\1/p' | head -1 || true)"
[ -n "$DOMAIN" ] || DOMAIN="$(cat "$APP_DIR/.voice-domain" 2>/dev/null || true)"

if [ "$FIRST_RUN" = 1 ]; then
  say "Let's set up Voice. Press Enter to accept the value in [brackets]."
  echo "  Tip: the app takes over the whole domain you pick. If your main domain"
  echo "  already has a website, create a subdomain first (cPanel → Domains), e.g. voice.${MAIN_DOMAIN:-yourdomain.com}"
  ask DOMAIN "Domain or subdomain to run it on" "${MAIN_DOMAIN:-}"
  [ -n "$DOMAIN" ] || die "A domain is required."
  ask ADMIN_EMAIL "Your email (becomes the admin login)" ""
  [ -n "$ADMIN_EMAIL" ] || die "An admin email is required."
  ask EL_KEY "ElevenLabs API key (sk_...)" ""
  ask TRC20 "USDT TRC20 wallet address (Enter to skip)" ""
  ask BEP20 "USDT BEP20 wallet address (Enter to skip)" ""
fi
[ -n "$DOMAIN" ] || die "Can't find the domain from the first install. Delete $APP_DIR/.env to set up again."

# ------------------------------------------------------------ node app
say "Preparing the Node.js app"
NODE_VERSION=""
if [ -d "$HOME/nodevenv/$APP_NAME" ]; then
  NODE_VERSION="$(ls "$HOME/nodevenv/$APP_NAME" | sort -V | tail -1)"
  ok "Node.js app already exists (Node $NODE_VERSION)"
else
  for v in 24 22; do
    if cloudlinux-selector create --json --interpreter nodejs --version "$v" --app-root "$APP_NAME" \
         --domain "$DOMAIN" --app-uri "/" --app-mode production --startup-file app.cjs >/dev/null 2>&1; then
      NODE_VERSION="$v"
      break
    fi
  done
  [ -n "$NODE_VERSION" ] || die "Couldn't create the Node.js app. Check that Node 24 or 22 is offered in cPanel → Setup Node.js App, and that $DOMAIN exists in cPanel."
  ok "Created the Node.js app on $DOMAIN with Node $NODE_VERSION"
fi
NODE_BIN="$HOME/nodevenv/$APP_NAME/$NODE_VERSION/bin/node"
[ -x "$NODE_BIN" ] || NODE_BIN="$(ls -d "$HOME"/nodevenv/"$APP_NAME"/*/bin/node 2>/dev/null | sort -V | tail -1)"
[ -x "$NODE_BIN" ] || die "Can't find the app's Node.js in ~/nodevenv/$APP_NAME."

cloudlinux-selector stop --json --interpreter nodejs --app-root "$APP_NAME" >/dev/null 2>&1 || true

# ------------------------------------------------------------- download
say "Downloading the latest version"
rm -rf "$WORK" && mkdir -p "$WORK/unpacked"
curl -fL --retry 3 -o "$WORK/voice.zip" "$ZIP_URL" || die "Download failed: $ZIP_URL"
if command -v unzip >/dev/null; then
  unzip -q "$WORK/voice.zip" -d "$WORK/unpacked"
else
  python3 -m zipfile -e "$WORK/voice.zip" "$WORK/unpacked" || die "Couldn't unzip (no unzip or python3)."
fi
[ -f "$WORK/unpacked/app.cjs" ] || die "The download doesn't look right (no app.cjs)."
ok "Downloaded"

# Replace the app files, keeping .env, data and logs.
mkdir -p "$APP_DIR"
for item in .next node_modules public lib scripts server.js app.cjs package.json NAMECHEAP.md .env.example app.js; do
  rm -rf "${APP_DIR:?}/$item"
done
cp -a "$WORK/unpacked/." "$APP_DIR/"
mkdir -p "$APP_DIR/data"
chmod 700 "$APP_DIR/data"
echo "$DOMAIN" > "$APP_DIR/.voice-domain"
ok "Installed into $APP_DIR"

# ---------------------------------------------------------------- .env
if [ "$FIRST_RUN" = 1 ]; then
  umask 077
  cat > "$APP_DIR/.env" <<EOF
ADMIN_EMAILS=$ADMIN_EMAIL
ELEVENLABS_API_KEY=$EL_KEY
USDT_TRC20_ADDRESS=$TRC20
USDT_BEP20_ADDRESS=$BEP20
DATABASE_PATH=./data/app.db
EOF
  umask 022
  ok "Saved your settings in $APP_DIR/.env"
else
  ok "Kept your existing settings (.env) and data"
fi

# ------------------------------------------------------------ cron job
say "Payment checker"
CRON_LINE="* * * * * cd $APP_DIR && $NODE_BIN --env-file=.env --experimental-strip-types scripts/scan-payments-once.ts >> $APP_DIR/payments.log 2>&1"
( crontab -l 2>/dev/null | grep -v "$APP_NAME/.*scan-payments-once" || true; echo "$CRON_LINE" ) | crontab -
ok "USDT payments are checked every minute"

# --------------------------------------------------------------- start
say "Starting"
cloudlinux-selector start --json --interpreter nodejs --app-root "$APP_NAME" >/dev/null 2>&1 \
  || cloudlinux-selector restart --json --interpreter nodejs --app-root "$APP_NAME" >/dev/null 2>&1 \
  || die "Couldn't start the app. Open cPanel → Setup Node.js App and press Start."
sleep 3
CODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 60 "${VOICE_CHECK_URL:-https://$DOMAIN/}" || true)"
rm -rf "$WORK"

if [ "$CODE" = "200" ]; then
  ok "https://$DOMAIN is up"
else
  echo "  (The site answered '$CODE'. The first start can take a minute; if it stays down, check $APP_DIR/stderr.log)"
fi

printf '\n\033[1;32mDone!\033[0m\n'
if [ "$FIRST_RUN" = 1 ]; then
  echo "  1. Open https://$DOMAIN and sign up with $ADMIN_EMAIL; you'll see an Admin button."
  echo "  2. Make sure HTTPS works (cPanel → SSL/TLS Status → Run AutoSSL) — the microphone needs it."
fi
echo "  To update later, paste the same command again."
