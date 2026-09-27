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
| GET | `/track/{trackingNumber}` | Rate limited. Returns status, `last_updated_at`, city-level `origin_area`/`destination_area` and `origin_country`/`destination_country`, `estimated_delivery` (only when the service has a configured estimate, flagged `is_estimate`), customer-safe `events[]`, `support_url`. No addresses, phones, payment data or internal notes. |
| POST | `/quotes` | Body: `origin{country, region?, postal_code?, city?}`, `destination{…}`, `service_id`, `units` (`imperial`\|`metric`), `parcels[{weight, length?, width?, height?}]`, `declared_value?`, `insured?`, `pickup_requested?`. Zones are resolved from the locations. → `201 {id, total, currency, breakdown[], chargeable_weight, weight_unit, origin_zone, destination_zone, expires_at}`; `422` if a location isn't served. |
| POST | `/webhooks/stripe` | Stripe events. Requires a valid `Stripe-Signature` (HMAC-SHA256 of `t.payload` with the endpoint secret, max 5 min old) → otherwise `401`. Duplicate event IDs → `200 {"outcome":"duplicate"}`. The Checkout Session is re-verified with Stripe's API before anything changes. |
| POST | `/webhooks/paystack` | Paystack events (`x-paystack-signature`, HMAC-SHA512). Same idempotency and re-verification. |

## Authenticated

| Method | Path | Roles | Notes |
|---|---|---|---|
| GET | `/auth/me` | any | Profile, role, business membership |
| GET | `/shipments` | any | Only shipments the caller may see (own; business per team role; rider's assignments; all for staff). `?status=`, `?per_page=` |
| POST | `/shipments` | any | Booking detail fields (see `ShipmentDetails::rules`): for each of `pickup_`/`delivery_`: `address, address2?, city, region?, postal_code?, country` (ISO-2), `lat?, lng?, place_id?`; contacts (phones validated for the address country, stored E.164); `units`, `parcels[{weight, length?, width?, height?}]`; for cross-border: `customs_contents_type, customs_description, customs_hs_code?` and `declared_value`. Plus `quote_id`, `payment_method` (`online`/`cod`/`invoice`), `idempotency_key`, `accept_terms`. The quote must match the details. Replaying the same `idempotency_key` returns the existing shipment with `200`. |
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
