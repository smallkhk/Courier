# Deploying to Namecheap cPanel (Stellar shared hosting)

This guide takes the site from this repository to a live HTTPS domain on a
Namecheap **Stellar / Stellar Plus / Stellar Business** plan. Everything below is
done from cPanel and its built-in **Terminal** (Advanced → Terminal). If your plan
has no Terminal, use SSH (Namecheap: *Advanced → SSH Access*), or run the
`composer install` step on your computer and upload the `vendor/` folder.

> Nothing here needs Node.js on the server: the compiled CSS/JS lives in
> `public/build/` and is committed to the repository.

---

## 0. Requirements checklist

| Item | Where |
|---|---|
| PHP **8.2 or newer** (8.3 recommended) | cPanel → *Select PHP Version* |
| PHP extensions: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `tokenizer`, `xml`, `ctype`, `curl`, `intl`, `gd` | same page → *Extensions* tab |
| MySQL / MariaDB database + user | cPanel → *MySQL Databases* |
| An email account to send from (e.g. `no-reply@yourdomain.com`) | cPanel → *Email Accounts* |
| SSL certificate | cPanel → *SSL/TLS Status* → *Run AutoSSL* (free) |
| Paystack account (business verified for live keys) | dashboard.paystack.com |
| Optional: Termii account + approved Sender ID for SMS | accounts.termii.com |

---

## 1. Create the database

1. cPanel → **MySQL Databases**.
2. *Create New Database*: e.g. `courier` → becomes `cpaneluser_courier`.
3. *Add New User*: e.g. `courier` with a long generated password → `cpaneluser_courier`.
4. *Add User To Database* → tick **ALL PRIVILEGES** is simplest; for least privilege
   grant: SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES,
   LOCK TABLES (DROP/ALTER/CREATE are needed only while running migrations).

## 2. Put the code on the server (outside `public_html`)

The application must live **outside** the web root so `.env`, `storage/` and
source files are never downloadable. Only the `public/` folder is served.

**Option A — cPanel Git™ Version Control (recommended):**

1. cPanel → **Git Version Control** → *Create*.
2. Clone URL: your repository URL (for a private GitHub repo, add a deploy key:
   cPanel → *SSH Access* → generate key → add the public key to GitHub →
   *Settings → Deploy keys*).
3. Repository Path: `/home/cpaneluser/courier` (NOT inside `public_html`).

**Option B — upload a zip:** download the repo as a zip, upload with
*File Manager* to `/home/cpaneluser/`, and extract to `/home/cpaneluser/courier`.

## 3. Install PHP dependencies

cPanel → **Terminal**:

```bash
cd ~/courier
composer install --no-dev --optimize-autoloader
```

If `composer` isn't found, use `php ~/composer.phar …` after downloading it from
getcomposer.org, or run the command locally and upload `vendor/`.

## 4. Configure `.env`

```bash
cd ~/courier
cp .env.example .env
php artisan key:generate
nano .env          # or edit with cPanel File Manager
```

Fill in at least: `APP_NAME`, `APP_URL=https://yourdomain.com`, `APP_ENV=production`,
`APP_DEBUG=false`, the `DB_*` values from step 1, the `MAIL_*` values (step 8),
the `PAYSTACK_*` keys (step 7). Then protect the file:

```bash
chmod 600 .env
```

Check it:

```bash
php artisan courier:check-config
```

## 5. Create tables, seed essentials, create your admin

```bash
php artisan migrate --force
php artisan db:seed --force              # message templates + DRAFT policy pages (safe to re-run)
php artisan courier:create-admin you@yourdomain.com "Your Name"
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

> **Do not** run `DemoSeeder` in production — it refuses to run anyway.

## 6. Point the domain at `public/`

The web server must serve `~/courier/public`.

**Main domain** (`public_html` is fixed): replace `public_html` with a symlink.

```bash
mv ~/public_html ~/public_html_old     # keep a backup of whatever was there
ln -s ~/courier/public ~/public_html
```

**Addon domain or subdomain:** cPanel → *Domains* → *Manage* → set the
**Document Root** to `courier/public`.

Make storage writable (usually already correct on cPanel):

```bash
chmod -R 775 ~/courier/storage ~/courier/bootstrap/cache
```

Visit `https://yourdomain.com/health` — you should see `"status":"ok"`.

## 7. Payments — Paystack

1. Paystack dashboard → **Settings → API Keys & Webhooks**.
2. Copy the **Secret** and **Public** keys into `.env` (`PAYSTACK_SECRET_KEY`,
   `PAYSTACK_PUBLIC_KEY`). Start with **test** keys (`sk_test_…`), place a test
   booking, then switch to **live** keys.
3. **Webhook URL**: `https://yourdomain.com/api/webhooks/paystack`
4. **Callback URL**: not required — the app sends it per transaction
   (`https://yourdomain.com/payments/callback`).
5. Set `PAYMENT_PROVIDER=paystack` and run `php artisan config:cache`.

How it's secured: checkout is initialised server-side; the browser redirect is
never trusted — every payment is verified with Paystack's *Verify Transaction* API
(amount, currency and reference must match); webhooks are checked with the
HMAC-SHA512 `x-paystack-signature` header, de-duplicated, and re-verified with the
API. Abandoned checkouts are reconciled by the scheduled job.

Offline payments (e.g. bank transfer to your account) can be confirmed by an admin
on the shipment page; this is audit-logged.

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

## 9. SMS (optional) — Termii

```
SMS_DRIVER=termii
TERMII_API_KEY=…
TERMII_SENDER_ID=YourBrand      # must be approved by Termii
```

Then enable *Send SMS notifications* in **Admin → System settings**. SMS goes only
to customers who consented (and to recipients for delivery-day messages).

## 10. The cron job (required)

Notifications, payment reconciliation, retries and data retention run from
Laravel's scheduler. cPanel → **Cron Jobs** → *Add New Cron Job*:

- Common settings: **Once Per Minute** (`* * * * *`)
- Command:

```
cd /home/cpaneluser/courier && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
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
`~/courier/storage/app/private`, which is outside the web root, under random names.
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

- Application logs: `~/courier/storage/logs/laravel-YYYY-MM-DD.log`
  (`LOG_CHANNEL=daily`). Configuration errors are logged as `CRITICAL CONFIG:` at startup.
- Uptime: point a free monitor (UptimeRobot, Better Stack…) at `https://yourdomain.com/health`
  (HTTP 200 = healthy, 503 = database down).
- Notification delivery: **Operations → Notifications log**.
- Optional error tracking: `composer require sentry/sentry-laravel` and set `SENTRY_LARAVEL_DSN`.

## 15. Updating the site

```bash
cd ~/courier
php artisan down --retry=60
git pull                                  # or cPanel Git → Update from Remote
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

Front-end changes: run `npm ci && npm run build` **on your computer** and commit
`public/build/`.

### Rollback

1. `php artisan down`
2. `git checkout <previous-tag-or-commit>` then `composer install --no-dev`
3. If the release included migrations: `php artisan migrate:rollback --step=N` —
   or restore the pre-deploy database dump if data was transformed.
4. `php artisan config:cache && php artisan up`

Always take a database dump before running migrations in production.

## 16. Go-live checklist

- [ ] `php artisan courier:check-config` reports no errors
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` uses https
- [ ] AutoSSL certificate active; http redirects to https (cPanel → *Domains* → Force HTTPS)
- [ ] `/health` returns ok and scheduler ok
- [ ] Paystack **live** keys set; webhook URL saved in Paystack; one real low-value payment tested and refunded
- [ ] Test email received (register a test account); SPF/DKIM valid
- [ ] SMS tested (if enabled)
- [ ] Zones, services, pricing rules, branches entered (Admin); DEMO data absent
- [ ] Terms, privacy and delivery policies replaced with lawyer-reviewed text (Admin → Website content)
- [ ] Settings reviewed: currency, guest booking, COD, proof rules, retention (Admin → System settings)
- [ ] Staff accounts created with the right roles; riders created and able to sign in on their phones
- [ ] Backups configured and a restore tested
- [ ] Map tile provider key configured and domain-restricted
