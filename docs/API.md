# JSON API

Base path: `/api`. All responses are JSON. Shipments are addressed by **tracking
number**, never by database ID.

**Authentication:** same-origin session cookie (sign in through `/login`) plus the
`X-CSRF-TOKEN` header for state-changing requests (the token is in
`<meta name="csrf-token">`). Webhooks are the only CSRF-exempt routes.

**Errors** use standard status codes with a consistent body:

```json
{ "message": "The given data was invalid.", "errors": { "field": ["Explanation"] } }
```

`401` not signed in · `403` role/permission denied · `404` not found *or not yours* ·
`422` validation / state-machine violation · `429` rate limited.

## Public

| Method | Path | Notes |
|---|---|---|
| GET | `/track/{trackingNumber}` | Rate limited. Returns status, `last_updated_at`, city-level `origin_area`/`destination_area`, `estimated_delivery` (only when the service has a configured estimate, flagged `is_estimate`), customer-safe `events[]`, `support_url`. No addresses, phones, payment data or internal notes. |
| POST | `/quotes` | Body: `service_id, origin_zone_id, destination_zone_id, parcels[{weight_kg, length_cm?, width_cm?, height_cm?}], declared_value?, insured?, pickup_requested?`. → `201 {id, total, currency, breakdown[], chargeable_weight_kg, expires_at}` |
| POST | `/webhooks/paystack` | Paystack events. Requires valid `x-paystack-signature` (HMAC-SHA512 of raw body with secret key) → otherwise `401`. Duplicate bodies → `200 {"outcome":"duplicate"}`. Payment state is re-verified with Paystack's API before anything changes. |

## Authenticated

| Method | Path | Roles | Notes |
|---|---|---|---|
| GET | `/auth/me` | any | Profile, role, business membership |
| GET | `/shipments` | any | Only shipments the caller may see (own; business per team role; rider's assignments; all for staff). `?status=`, `?per_page=` |
| POST | `/shipments` | any | Booking detail fields (see `BookingController::detailRules`) + `quote_id`, `payment_method` (`online`/`cod`/`invoice`), `idempotency_key`, `accept_terms`. The quote must match the details. Replaying the same `idempotency_key` returns the existing shipment with `200`. |
| GET | `/shipments/{tn}` | owner/staff | Includes `events[]`; `internal_note` only for staff |
| POST | `/shipments/{tn}/cancel` | owner/staff | Allowed statuses are configured in settings; paid shipments must be cancelled by staff |
| POST | `/shipments/{tn}/payment` | owner | Initialise or resume payment → `{reference, checkout_url}` |
| GET | `/payments/{reference}` | payer/business finance/staff | |
| POST | `/shipments/{tn}/events` | rider (own assignment), dispatcher, admin | `{status, public_description?, internal_note?, location?}`. Enforced by the state machine. `delivered` and `delivery_attempted` must use the endpoints below. |
| POST | `/shipments/{tn}/delivery-attempts` | rider (own), dispatcher, admin | multipart: `reason` (`recipient_unavailable`, `address_issue`, `recipient_declined`, `access_issue`, `other`), `note?`, `evidence?` (jpg/png/webp) |
| POST | `/shipments/{tn}/proof-of-delivery` | rider (own), dispatcher, admin | multipart: `recipient_name`, `signature?` (PNG data URL), `photo?` + `photo_consent`, `delivery_code?`, `cod_amount_collected?`, `note?`. Requirements come from settings. |
| POST | `/shipments/{tn}/assign-rider` | dispatcher, admin | `{rider_id, leg: pickup|delivery, note?}` — checks rider is active and under capacity; ends previous assignment |
| GET | `/rider/assignments` | rider | Open assignments with pickup/drop-off details |
| POST | `/rider/assignments/{id}/accept` | rider | |
| POST | `/rider/availability` | rider | `{on_duty: bool}` — going off duty stops location sharing |
| POST | `/rider/location-sharing` | rider | `{sharing: bool, consent?: bool}` — requires on duty and consent |
| POST | `/rider/location` | rider | `{lat, lng, accuracy?, recorded_at?}` — only while on duty with sharing on; rejects invalid coordinates, accuracy > 5 km and implausible speed (> 180 km/h). Throttled 12/min. |
| GET | `/notifications` | any | Your notification history |
| GET/POST | `/support/tickets` | any | `{category, subject, message, tracking_number?}` |
| POST | `/support/tickets/{reference}/messages` | ticket owner, staff | `{body, internal?}` (internal only for staff) |
| GET | `/admin/dashboard` | dispatcher, admin | Operational counts |
| GET | `/admin/shipments` | dispatcher, admin | `?status=`, `?q=` (tracking/phone/email) |
| GET | `/admin/riders` | dispatcher, admin | |
| POST | `/admin/riders` | admin | Creates a rider and emails a set-password link |
| GET | `/admin/audit-logs` | admin | `?action=` prefix |

Configuration CRUD (services, pricing rules, zones, branches, templates, content,
FAQs), users and settings are managed through the admin web UI (`/admin/...`), which
uses the same server-side validation and audit logging.
