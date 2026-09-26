# Courier — parcel booking, tracking & delivery management

A complete, database-backed courier website for **customers, business clients,
delivery riders, dispatchers and administrators**. Web only (no native app): every
workflow runs in responsive pages on phones, tablets and desktops.

Built to run on **Namecheap cPanel shared hosting** (PHP + MySQL + one cron job).
See **[DEPLOYMENT.md](DEPLOYMENT.md)** for step-by-step go-live instructions.

---

## Stack (decision record)

| Concern | Choice | Why |
|---|---|---|
| Language / framework | **PHP 8.2+ / Laravel 12** | Runs natively on cPanel; mature auth, validation, CSRF, migrations |
| Database | **MySQL / MariaDB** (InnoDB, utf8mb4) | Available on every cPanel plan; transactional |
| UI | **Blade + Tailwind CSS v4 + Alpine.js**, built with Vite | Server-rendered, fast, accessible; assets pre-built and committed so the server needs no Node.js |
| Design system | Tokens generated with the *ui-design-system* skill ("modern": Inter, 8 px radius, 8 pt grid, layered shadows), hand-tuned for WCAG AA around brand blue `#1447E6` | `resources/css/app.css` |
| Imagery & motion | Courier-themed CC0 photos (Openverse/rawpixel) self-hosted as optimised WebP in `public/images` — see `public/images/CREDITS.md`; duotone illustrated icons (`<x-illus>`); CSS scroll-reveal, Ken Burns hero, animated route lines; all motion disabled for `prefers-reduced-motion` | Swap in your own brand photography by replacing the files with the same names |
| Branding extras | Branded loading screen (first page of each visit only, max ~3 s), navigation progress bar, animated logo, branded animated error pages (403/404/419/429/500/503), confetti on booking confirmation, floating WhatsApp chat button (set a number in Admin → Settings), installable web app manifest + icons | Loading screen and all motion are skipped for `prefers-reduced-motion` |
| Maps | **Leaflet** + configurable XYZ tiles (OpenStreetMap by default) | No mandatory API key; swap to a commercial tile provider in production |
| Payments | **Paystack** (server-side init, API verification, signed idempotent webhooks) + a clearly-labelled **sandbox** gateway for development | Nigerian market; sandbox refused in production |
| Email | Laravel Mail over SMTP (cPanel mailbox or any provider) | `log` driver in development |
| SMS | **Termii** HTTP driver; `log` driver in development | |
| Background jobs | DB-backed **notification outbox** + Laravel **scheduler** triggered by one cPanel cron | Shared hosting can't run daemons |
| Files | Private local disk `storage/app/private`, streamed through authorised routes | Proofs are never public |
| Tests | PHPUnit (unit + feature, against MySQL) and **Playwright** end-to-end | |

Architecture is a modular monolith: controllers are thin; business rules live in
`app/Services` (pricing, booking, workflow, dispatch, delivery, payments,
notifications, location, invoices, bulk import, reports) and are shared by the web
UI and the JSON API. **Authorisation is enforced on the server** (route role
middleware, business-membership middleware, ownership scopes and per-action checks
inside services) — hiding a button is never the only protection.

## Feature map

**Public site** — home (value proposition, booking CTA, tracking form), services,
pricing/quote calculator, coverage areas, branch & pickup-point directory with map
+ accessible list, tracking (with optional recipient verification), about, FAQ,
contact/support, sign-up/sign-in/password reset/email verification, terms,
privacy and delivery/claims policy pages (admin-editable).

**Booking** — guided wizard (pickup → delivery → parcels & service) with saved
addresses, route/zone validation, server-side quote with full breakdown and
expiry, review & terms, guest or signed-in booking, idempotent confirmation,
pending shipment + payment record, sandbox/Paystack checkout, recoverable after
interruption, unique unguessable tracking number, confirmation notification.

**Customer portal** — dashboard, shipments (search/filter), shipment detail with
timeline and proof of delivery, cancel (when allowed), saved addresses, payments
& printable receipts, notification history and preferences (email/SMS consent),
profile/password, support tickets.

**Business portal** — registration & approval, company dashboard, single and bulk
(CSV template → validation preview with duplicate detection → confirm) shipments,
shipment list with search/filters/sort/CSV export, pay many shipments in one
checkout, invoices (monthly, for approved credit terms) with online payment,
reports, team members with roles (owner, admin, shipper, finance, viewer), company
profile, pickup addresses. Negotiated rates via business-specific pricing rules.
Businesses are strictly isolated from each other.

**Rider portal (web)** — on/off duty, explicit location-sharing consent with live
status/stop control, job list, job detail with call/navigate links, accept/decline,
permitted status updates, proof of delivery (recipient name, signature pad,
photo with consent, optional recipient code, COD cash amount), failed-attempt form
with evidence, history.

**Operations (dispatcher + admin)** — dashboard with live counts, shipment search
(tracking/name/phone/email) with filters, sorting, CSV export, shipment detail with
internal timeline, status changes, notes, rider assignment/reassignment with
rule-based suggestions, dispatch board with workload, rider map (live vs.
last-known clearly labelled), exceptions queue (retry / contact / hold / return),
payments (re-verify, admin refunds), cash-on-delivery reconciliation, support
tickets (assign, prioritise, internal notes, customer replies), customers,
businesses (approve, terms, invoices), riders, reports with metric definitions,
notification log with retry.

**Administration** — services, pricing rules, coverage zones (city lists, remote
flag, pickup/delivery availability), branches (hours, holiday closures,
coordinates), message templates, website content, FAQs, users & roles (suspend
signs out everywhere), status-workflow reference, audit-log search, system
settings with integration status.

## Local development

Prerequisites: PHP 8.2+ with `pdo_mysql mbstring openssl fileinfo intl gd`,
Composer 2, MySQL 8 / MariaDB 10.6+, Node 20+ (only to rebuild CSS/JS).

```bash
composer install
cp .env.example .env
# edit .env: APP_ENV=local, APP_DEBUG=true, APP_URL=http://127.0.0.1:8000,
#            DB_* for your local database, PAYMENT_PROVIDER=sandbox,
#            MAIL_MAILER=log, SMS_DRIVER=log, SESSION_SECURE_COOKIE=false
php artisan key:generate
php artisan migrate --seed --seeder=DemoSeeder    # DEMO data — never in production
npm install && npm run build                      # or `npm run dev` for hot reload
php artisan serve
```

Run the scheduler locally in another terminal: `php artisan schedule:work`.

Demo logins (password `password123`): `admin@example.com`,
`dispatcher@example.com`, `customer@example.com`, `business@example.com`,
`rider@example.com`, `rider2@example.com`. Demo zones, services, branches and
prices are all prefixed **DEMO** and are placeholders — not real rates.

In development the site shows a yellow **DEVELOPMENT MODE** banner: payments use
the sandbox checkout (you choose success / decline / abandon) and emails/SMS are
written to `storage/logs` instead of being sent.

## Testing

```bash
# Unit + feature tests (needs a MySQL database named courier_test; see phpunit.xml)
php artisan test

# End-to-end tests in Chromium (spins up its own server on a fresh DEMO database)
npx playwright install chromium      # first time only
npm run test:e2e
```

Covered: pricing (volumetric weight, minimums, insurance, tax, rule precedence,
unserviceable routes), tracking-number format/checksum/uniqueness, status
state-machine & role rules, money arithmetic, booking and quote creation,
idempotent double-submit, guest access, sandbox payment success/failure/retry,
Paystack webhook signature, duplicate events, amount/currency mismatch,
webhook-body-not-trusted, public tracking privacy & rate limits, rider
assignment/reassignment & ownership, proof-of-delivery upload & file
authorisation, failed delivery → retry/return, notification dedupe, retry with
backoff, permanent failures, disabled channels, rider location consent/validation/
retention, portal & API role checks, spam protection, login throttling, refunds
(admin-only, audited), business data isolation, team permissions, bulk import,
invoicing. E2E: customer books & pays → tracks; dispatcher assigns; rider
updates & signs; customer sees timeline & proof; unauthorised access blocked;
payment failure; failed delivery resolved by ops.

No real payment credentials are used in any test (Paystack HTTP calls are faked).

## Project layout

```
app/Enums/ShipmentStatus.php         status state machine (transitions + roles)
app/Services/                        pricing, booking, workflow, dispatch, delivery,
                                     payments/, notifications/, location, invoices,
                                     bulk import, reports, private file storage
app/Http/Controllers/{Account,Business,Rider,Ops,Admin,Api,Auth}
app/Support/                         settings, money, tracking numbers, audit, CSV,
                                     navigation, admin resource definitions, config check
database/migrations/                 full schema (FKs, indexes, uniqueness)
database/seeders/                    EssentialSeeder (templates, draft pages), DemoSeeder
resources/css/app.css                design tokens + component classes
resources/js/app.js                  Alpine components (maps, location sharing, signature…)
resources/views/                     Blade pages & components
routes/web.php, routes/api.php       web and JSON API routes
routes/console.php                   scheduled jobs
docs/API.md, docs/STATUS_WORKFLOW.md
```

## Security & privacy summary

- Passwords hashed with bcrypt (cost 12); sessions are DB-backed, encrypted,
  `HttpOnly`, `Secure`, `SameSite=Lax`; CSRF on every form and same-origin API call.
- Server-side validation everywhere; Blade output escaping; parameterised queries
  (Eloquent); CSV exports neutralise spreadsheet formulas.
- Rate limits: login, password reset, registration/contact, public tracking
  (and recipient verification), quotes, booking, rider location, API.
- Bot protection: honeypot + signed timing token on public forms.
- Tracking numbers: 60 bits of CSPRNG entropy + checksum, DB-unique, never
  sequential; unknown and malformed numbers return identical 404s.
- Public tracking shows only status, timeline, city-level areas and estimates —
  no addresses, phone numbers, payment data, internal notes or rider identity.
- Uploads: type/size/content checks, random names, private disk, authorised
  streaming with `no-store`; staff access to proofs is audit-logged.
- Payments: no card data ever touches the server; provider verification is the
  source of truth; idempotent webhooks; refunds admin-only and audited.
- Audit log for staff logins, status changes, assignments, refunds, settings,
  configuration, role changes, customer-record access and exports; secrets are
  redacted.
- Location: collected only on duty, with consent, while sharing is on;
  coarsened to ~1 km for customers; never labelled live when stale; auto-deleted
  after the configured retention period.
- Security headers (nosniff, frame options, referrer policy, permissions policy,
  HSTS over HTTPS).
- Nigerian data-protection law (NDPA 2023) and any other applicable law: obtain
  qualified legal advice; the policy pages ship as clearly marked drafts.

## Owner decisions required before launch

These are business decisions the software deliberately does **not** invent. Each
is configurable in the admin area or `.env`:

- Business name, logo, colours (edit `--color-brand-*` in `resources/css/app.css`), contact details
- Real delivery zones, city lists and branch addresses/hours
- Services and any delivery-time commitments (leave transit days blank to show no estimate)
- Pricing, surcharges, taxes, discounts, minimum charges; negotiated business rates
- Supported currency (default NGN) and payment methods
- Guest booking on/off; account verification rules
- Cancellation, refund, return and claims policies (Website content)
- Whether to offer **cash on delivery**, and the cash-handling/reconciliation procedure
  (the software tracks collected → remitted → reconciled / discrepancy)
- Proof-of-delivery requirements (signature / photo / recipient code)
- Rider assignment policy and location-sharing policy; location retention period
- Data-retention periods for other personal data
- Notification channels, wording and consent rules
- Business account approval criteria and credit terms
- Hosting, Paystack, email, SMS and map-tile provider accounts

## Known limits / not included

- **ETAs** are service-level estimates (owner-configured transit days in business
  days), not live routing ETAs. No live traffic routing is implemented.
- **Rider suggestion** is a simple rule (in zone → on duty → fewest open jobs); it is
  not route optimisation, and there is no automatic assignment.
- **Browser location**: phones may pause updates when locked/backgrounded; the UI
  labels stale positions accordingly.
- Flutterwave is not implemented; add another `PaymentGateway` implementation to support it.
- Holiday closures are displayed but not yet excluded from delivery estimates.
- Invoice PDFs use the browser's *Print → Save as PDF*.
