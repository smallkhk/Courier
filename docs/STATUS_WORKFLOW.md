# Shipment status workflow

Source of truth: `app/Enums/ShipmentStatus.php` (`transitions()`); enforced by
`App\Services\ShipmentWorkflow::transition()`, the only code path that changes a
shipment's status. Every change appends a `shipment_events` row (append-only —
updates/deletes are refused) recording: status, previous status, timestamp,
location, customer-safe description, internal note (staff only), actor, source
(`customer`, `rider`, `dispatcher`, `admin`, `system`) and the proof ID for deliveries.

Actor roles: **system** (payment verification, automatic steps), **customer**,
**rider** (only for shipments assigned to them), **dispatcher**, **admin**.

```
draft ─► pending_payment ─► booked ─► pickup_scheduled ─► rider_assigned ─► picked_up
                              │                                               │
                              └──────────► at_origin_facility ◄───────────────┤
                                                   │                          │
                                              in_transit ─► at_destination_facility
                                                   │                  │
                                                   └──► out_for_delivery ◄┘
                                                          │    │     │
                                              delivered ◄─┘    │     └─► delivery_exception
                                                               ▼                 │
                                                     delivery_attempted ◄────────┤
                                                               │                 │
                                          (ops) retry → at_destination_facility / out_for_delivery
                                          (ops) hold → on_hold
                                          (ops) return → return_initiated → return_in_transit → returned_to_sender
cancelled: from pending_payment/booked/pickup_scheduled (customer or admin), later stages admin only
```

| From | To | Who |
|---|---|---|
| draft | pending_payment | system, customer |
| draft | booked | system |
| pending_payment | booked | system (verified payment), admin (offline payment, audited) |
| pending_payment / booked / pickup_scheduled | cancelled | customer, admin |
| booked | pickup_scheduled, at_origin_facility, on_hold | dispatcher, admin |
| booked / pickup_scheduled | rider_assigned | system, dispatcher, admin (on assignment) |
| rider_assigned | picked_up, out_for_delivery | rider, dispatcher, admin |
| picked_up | at_origin_facility, in_transit, out_for_delivery | rider, dispatcher, admin |
| at_origin_facility | in_transit, out_for_delivery | rider, dispatcher, admin |
| in_transit | at_destination_facility, out_for_delivery | rider, dispatcher, admin |
| at_destination_facility | out_for_delivery | rider, dispatcher, admin |
| at_destination_facility | return_initiated | dispatcher, admin |
| out_for_delivery | delivered | rider, dispatcher, admin — **requires proof of delivery** |
| out_for_delivery | delivery_attempted, delivery_exception | rider, dispatcher, admin |
| delivery_attempted | out_for_delivery, at_destination_facility | rider, dispatcher, admin |
| delivery_attempted | delivery_exception, return_initiated, on_hold | dispatcher, admin |
| delivery_exception | out_for_delivery, at_destination_facility | rider, dispatcher, admin |
| delivery_exception | return_initiated, on_hold | dispatcher, admin |
| return_initiated | return_in_transit | rider, dispatcher, admin |
| return_in_transit | returned_to_sender | rider, dispatcher, admin |
| on_hold | booked, at_origin_facility, at_destination_facility, out_for_delivery, return_initiated | dispatcher, admin |
| on_hold | cancelled | admin |
| *(any stage)* | on_hold | dispatcher, admin (where listed) |

`delivered`, `returned_to_sender` and `cancelled` are final.

Assigning a rider after pickup does **not** move the status backwards: it adds a
timeline note ("A delivery agent has been assigned to the next leg") instead.

## Proof-of-delivery rules (Admin → System settings)

A recipient name is always required. Optionally also: signature, photo (with
recipient consent), and a 6-digit recipient delivery code sent to the recipient at
booking. For cash-on-delivery shipments the rider must record the cash collected.

## Notifications per status

`pickup_scheduled`, `rider_assigned`, `picked_up`, `in_transit`,
`out_for_delivery`*, `delivery_attempted`/`delivery_exception`* (as
"delivery_failed"), `delivered`*, `return_initiated`, `returned_to_sender`,
`cancelled`, plus `booking_created`, `payment_confirmed`, `payment_failed`,
`support_updated`. (*also sent to the recipient.) Templates are editable in
Admin → Message templates.

## Changing the workflow

Edit `transitions()` in `app/Enums/ShipmentStatus.php`, update this document, and
run `php artisan test` — `tests/Unit/ShipmentStatusTest.php` checks the machine is
complete and that key safety rules hold (e.g. customers can't confirm their own
payment, nothing can skip to delivered).
