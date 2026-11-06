# Launching ExpressShipping on Namecheap cPanel

This guide takes the site live on a Namecheap **Stellar / Stellar Plus / Stellar
Business** plan using only cPanel in your browser. You do **not** need SSH,
Composer or Node.js: the release package already contains everything.

After launch, new versions install from **Admin → Software updates** with one
button (section 15).

---

## 0. Requirements checklist

| Item | Where |
|---|---|
| PHP **8.2 or newer** (8.3 recommended) | cPanel → *Select PHP Version* |
| PHP extensions: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `tokenizer`, `xml`, `ctype`, `curl`, `intl`, `gd`, `zip` | same page → *Extensions* tab |
| MySQL / MariaDB database + user | cPanel → *MySQL Databases* |
| An email account to send from (e.g. `no-reply@yourdomain.com`) | cPanel → *Email Accounts* |
| SSL certificate | cPanel → *SSL/TLS Status* → *Run AutoSSL* (free) |
| Stripe account (business verified for live payments) | dashboard.stripe.com |
| Google Maps Platform API key (address search) | console.cloud.google.com |
| Optional: Twilio account + A2P 10DLC registration for US SMS | console.twilio.com |

## 1. Download the release package

From the repository's **Releases** page, download `expressshipping-X.Y.Z.zip`
(about 27 MB). It contains the production code, its PHP libraries and the
compiled design. It contains **no demo data, no tests and no `.env`**.

## 2. Create the database

1. cPanel → **MySQL Databases**.
2. *Create New Database*: e.g. `shipping` → becomes `cpaneluser_shipping`.
3. *Add New User*: e.g. `shipping` with a long generated password.
4. *Add User To Database* → **ALL PRIVILEGES**.

## 3. Upload and extract

Pick the folder your domain serves (its **document root**, shown in cPanel →
*Domains*). For the main domain this is `public_html`; for an addon domain or
subdomain it is the folder you chose, e.g. `expressshipping.co`.

1. cPanel → **File Manager** → open that folder (empty it first if it only holds
   the default cPanel placeholder files).
2. *Upload* the ZIP, then right-click it → *Extract*. Delete the ZIP afterwards.

The included `.htaccess` sends every request into the `public/` folder, so
`.env`, `storage/` and the code itself can never be downloaded (verified: they
return 404). *Even better, if cPanel → Domains lets you edit the domain's
document root, point it at the `public/` sub-folder; the result is the same.*

## 4. Create `.env`

In File Manager, copy `.env.example` to `.env` (enable *Show Hidden Files* in
Settings) and edit it:

| Setting | Value |
|---|---|
| `APP_URL` | `https://yourdomain.com` |
| `APP_KEY` | a random key, `base64:` followed by 32 random bytes in base64 — use the prepared `.env` you were given, or any Laravel key generator |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | from step 2 |
| `INSTALL_TOKEN` | a long random code (at least 20 characters) — you type it once in the installer |
| `MAIL_*`, `SUPPORT_EMAIL` | your cPanel email account (section 8) |
| Stripe, Google Maps, Twilio | sections 7, 7b and 9 — can be added after launch |

Keep `APP_ENV=production` and `APP_DEBUG=false`.

## 5. Choose PHP and run the installer

1. cPanel → **Select PHP Version** → **8.3**, and tick the extensions from step 0.
2. Make sure SSL is active (cPanel → *SSL/TLS Status* → *Run AutoSSL*).
3. Visit `https://yourdomain.com` — you are sent to **/install**. It checks the
   server, then asks for the setup code, your business name, support email and
   your administrator account.
4. Press **Install ExpressShipping**. It creates the tables, the message templates
   and draft policy pages, and your admin account. **No demo data is created.**

The installer locks itself as soon as an administrator exists. You can now remove
`INSTALL_TOKEN` from `.env`.

## 6. Finish the launch checklist

Sign in and open **Admin → System settings**. The *Launch checklist* at the top
shows what's left: delivery areas, services, prices, policies, Stripe, email, the
cron job and Google address search, each with a link to where it's done.

## 7. Payments — Stripe (recommended for US & international)

1. Create/verify your business at dashboard.stripe.com and enable the payment methods you want
   (cards are on by default; turn on Apple Pay / Google Pay / Link under **Settings → Payment methods**).
2. **Developers → API keys**: copy the secret and publishable keys into `.env`
   (`STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`). Start with **test** keys (`sk_test_…`).
3. **Developers → Webhooks → Add endpoint**: URL `https://yourdomain.com/api/webhooks/stripe`, events:
   `checkout.session.completed`, `checkout.session.async_payment_succeeded`,
   `checkout.session.async_payment_failed`, `checkout.session.expired`. Copy the **Signing secret**
   into `STRIPE_WEBHOOK_SECRET`.
4. Set `PAYMENT_PROVIDER=stripe` and place a test booking with
   card `4242 4242 4242 4242`. Then switch to live keys and a live webhook endpoint.

How it's secured: Checkout Sessions are created server-side with an idempotency key; the browser
redirect is never trusted — every payment is verified with Stripe's API (amount, currency and
reference must match); webhooks are checked against the `Stripe-Signature` HMAC with a 5-minute
replay window, de-duplicated by event ID, and re-verified with the API. Refunds are issued against
the PaymentIntent. Abandoned checkouts are reconciled by the scheduled job.

**Sales tax**: pricing rules have a tax % field. US sales tax on shipping varies by state; get advice,
or enable Stripe Tax separately. **Paystack** remains available (`PAYMENT_PROVIDER=paystack`, webhook
`https://yourdomain.com/api/webhooks/paystack`) if you also serve African markets.

Offline payments (e.g. bank transfer / ACH to your account) can be confirmed by an admin on the
shipment page; this is audit-logged.

## 7b. Address search & maps — Google Maps Platform

1. console.cloud.google.com → create a project → enable billing (Google gives a monthly free usage
   allowance; set a **budget alert** under Billing → Budgets).
2. **APIs & Services → Library**: enable **Maps JavaScript API** and **Places API (New)**.
3. **Credentials → Create credentials → API key**, then *Edit*:
   - Application restrictions: **Websites** → add `https://yourdomain.com/*` (and `https://www.yourdomain.com/*`).
   - API restrictions: restrict to *Maps JavaScript API* and *Places API (New)*.
   - Optionally set per-day quotas under each API's *Quotas* page.
4. Put the key in `.env`: `GOOGLE_MAPS_API_KEY=…` and `ADDRESS_AUTOCOMPLETE=google`.

This is a **browser** key (it is visible in the page by design); the website restriction is what
protects it. Without a key the site uses OpenStreetMap search (Photon) — fine for testing, but its
public server is fair-use only. Manual address entry with every country always works.

## 8. Email

Use the cPanel mailbox you created (cPanel → *Email Accounts* → *Connect Devices*
shows the server name and ports):

```
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=mail.yourdomain.com       # or server hostname shown in cPanel
MAIL_PORT=465
MAIL_USERNAME=no-reply@yourdomain.com
MAIL_PASSWORD=…
MAIL_FROM_ADDRESS=no-reply@yourdomain.com
```

Improve deliverability: cPanel → **Email Deliverability** → make sure SPF and DKIM
show *Valid*. Shared-host SMTP has hourly sending limits; for high volume use a
transactional provider (Mailgun, Postmark, Brevo…) with its SMTP credentials.

## 9. SMS (optional) — Twilio

```
SMS_DRIVER=twilio
TWILIO_SID=AC…
TWILIO_AUTH_TOKEN=…
TWILIO_FROM=+12125550100            # or TWILIO_MESSAGING_SERVICE_SID=MG… (recommended)
```

US carriers require **A2P 10DLC registration** (or toll-free number verification) before business
SMS is delivered — complete it in the Twilio Console. Then enable *Send SMS notifications* in
**Admin → System settings**. SMS goes only to customers who consented (and to recipients for
delivery-day messages). For Africa you can use `SMS_DRIVER=termii` instead.

## 10. The cron job (required)

Notifications, payment reconciliation, retries and data retention run from
Laravel's scheduler. It also finishes one-click updates if your browser closed
mid-update. cPanel → **Cron Jobs** → *Add New Cron Job* (use the folder you
extracted into in step 3):

- Common settings: **Once Per Minute** (`* * * * *`)
- Command:

```
cd /home/cpaneluser/public_html && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

(Check the PHP path in cPanel → *Select PHP Version*; on Namecheap it is typically
`/usr/local/bin/php` or `/opt/cpanel/ea-php83/root/usr/bin/php`.)

`/health` reports `"scheduler":"ok"` once the cron has run. Admin → System settings
also shows when it last ran.

What it runs:

| Job | Frequency |
|---|---|
| `notifications:send` — deliver queued email/SMS with retry + backoff | every minute |
| `payments:reconcile` — verify/expire abandoned checkouts | every 10 minutes |
| `courier:prune` — delete rider location history past retention, old quotes | daily 02:30 |
| `queue:work --stop-when-empty` | every minute |
| `courier:finish-update` — completes an update left half-way | every minute |
| check GitHub for a newer release (shows *Update available* on the admin dashboard) | hourly |

## 11. Maps

Branch maps and the rider map use Leaflet. The default tile server
(`tile.openstreetmap.org`) is for light use only — its usage policy forbids heavy
traffic. For production create a key with a tile provider (MapTiler, Stadia Maps,
Thunderforest…), **restrict the key to your domain** in their dashboard, set a
usage cap/billing alert, and set:

```
MAP_TILE_URL=https://api.maptiler.com/maps/streets-v2/256/{z}/{x}/{y}.png?key=YOUR_KEY
MAP_TILE_ATTRIBUTION="&copy; MapTiler &copy; OpenStreetMap contributors"
```

No geocoding API is required: addresses are validated against the coverage zones
and city lists you configure.

## 12. Private file storage

Proof-of-delivery photos and signatures are stored in
`storage/app/private` (never web-accessible: every request is served from `public/`), under random names.
They are streamed only through authorised routes. Do **not** run
`php artisan storage:link` for these files. Include `storage/app/private` in backups.

To move files to S3-compatible storage later, configure the `s3` disk in
`config/filesystems.php` and change `FileStorage::DISK`.

## 13. Backups & restore

- cPanel → **JetBackup** / **Backup** (Namecheap keeps automatic backups; check your
  plan's retention). Download a full backup after launch.
- Additionally, a nightly database dump via a second cron job:

```
0 3 * * * mysqldump --single-transaction -u cpaneluser_courier -p'DB_PASSWORD' cpaneluser_courier | gzip > /home/cpaneluser/backups/courier-$(date +\%F).sql.gz
```

(create `~/backups` first; delete old files periodically.)

**Restore test** (do this once before launch): create a scratch database, then
`gunzip < backup.sql.gz | mysql -u USER -p SCRATCH_DB`, point a copy of `.env` at it
and run `php artisan migrate:status`.

## 14. Logs & monitoring

- Application logs: `storage/logs/laravel-YYYY-MM-DD.log` in the site folder
  (`LOG_CHANNEL=daily`). Configuration errors are logged as `CRITICAL CONFIG:` at startup.
- Uptime: point a free monitor (UptimeRobot, Better Stack…) at `https://yourdomain.com/health`
  (HTTP 200 = healthy, 503 = database down).
- Notification delivery: **Operations → Notifications log**.
- Optional error tracking: `composer require sentry/sentry-laravel` and set `SENTRY_LARAVEL_DSN`.

## 15. Updating the site — one click

**Admin → Software updates** shows your version and the newest release. When an
update is available (the admin dashboard shows a banner too), read the notes,
enter your password and press **Install update**. What happens:

1. The package is downloaded from GitHub and its **SHA-256 checksum verified**;
   a package that fails the check, or contains unsafe file paths, is rejected
   before anything changes.
2. Every file that will change is **backed up**, and the database is dumped with
   `mysqldump` when the server allows it (shown under *Server checks*).
3. The site shows a short maintenance page while the new files are copied in.
   `.env`, `storage/` (uploads, logs) and your data are never touched.
4. A fresh request runs the database migrations, clears caches and brings the
   site back. If any step fails, the previous files are restored automatically.

If the browser is closed mid-update, the cron job finishes it within two
minutes. **Restore a previous version** on the same page puts back the program
files from any of the last three backups (the database is not changed).

Private repository? Set `UPDATE_GITHUB_TOKEN` in `.env` to a fine-grained token
with read-only *Contents* access.

### How releases are published (developers)

1. Make the change, bump `VERSION` (e.g. `1.0.1`) and add `docs/releases/1.0.1.md`.
2. Push a tag `v1.0.1`. The **Release** workflow runs the full test suite, builds
   `expressshipping-1.0.1.zip` with `scripts/build-release.sh` and publishes it
   with its `.sha256` file. A release that fails its tests is never published.

### Manual recovery

If the site is ever stuck on the maintenance page, delete
`storage/framework/down` in File Manager. Backups live in
`storage/app/updates/backups/<from>-to-<to>-<date>/` (`files/` holds the
replaced files, `database.sql.gz` the database dump).

## 16. Go-live checklist

- [ ] Admin → System settings: launch checklist complete, no red configuration warnings
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` uses https
- [ ] AutoSSL certificate active; http redirects to https (cPanel → *Domains* → Force HTTPS)
- [ ] `/health` returns ok and scheduler ok
- [ ] Stripe **live** keys and webhook signing secret set; one real low-value payment tested and refunded
- [ ] Google Maps key restricted to your domain and APIs; budget alert set
- [ ] Test email received (register a test account); SPF/DKIM valid
- [ ] SMS tested (if enabled); Twilio A2P 10DLC / toll-free verification approved
- [ ] Coverage zones (countries/states/ZIP prefixes), services, pricing rules, branches entered (Admin); no demo data (the installer never creates any)
- [ ] Settings reviewed: home country, currency, timezone, units (Admin → System settings)
- [ ] Terms, privacy and delivery policies replaced with lawyer-reviewed text (Admin → Website content)
- [ ] Settings reviewed: currency, guest booking, COD, proof rules, retention (Admin → System settings)
- [ ] Staff accounts created with the right roles; riders created and able to sign in on their phones
- [ ] Backups configured and a restore tested
- [ ] Map tile provider key configured and domain-restricted
