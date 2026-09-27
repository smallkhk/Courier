<?php

/*
| Integration configuration comes from the environment (secrets never live in code).
| Business rules (currency, guest booking, proof rules, retention...) are stored in the
| `settings` table and edited by administrators; the values below are only defaults
| used until an administrator saves a value.
*/

return [
    'payments' => [
        // stripe | paystack | sandbox   ("sandbox" is refused when APP_ENV=production)
        'provider' => env('PAYMENT_PROVIDER', 'sandbox'),
        'stripe' => [
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'base_url' => env('STRIPE_BASE_URL', 'https://api.stripe.com'),
        ],
        'paystack' => [
            'public_key' => env('PAYSTACK_PUBLIC_KEY'),
            'secret_key' => env('PAYSTACK_SECRET_KEY'),
            'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
        ],
        // Minutes a pending online payment stays open before it is marked cancelled.
        'pending_ttl_minutes' => (int) env('PAYMENT_PENDING_TTL_MINUTES', 60),
    ],

    'sms' => [
        // log | twilio | termii
        'driver' => env('SMS_DRIVER', 'log'),
        'twilio' => [
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_AUTH_TOKEN'),
            'from' => env('TWILIO_FROM'),                          // e.g. +12125550100 (a Twilio number)
            'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'), // recommended for US A2P 10DLC
        ],
        'termii' => [
            'api_key' => env('TERMII_API_KEY'),
            'sender_id' => env('TERMII_SENDER_ID'),
            'base_url' => env('TERMII_BASE_URL', 'https://api.ng.termii.com'),
            'channel' => env('TERMII_CHANNEL', 'generic'),
        ],
    ],

    'address_autocomplete' => [
        // google = Google Places (needs GOOGLE_MAPS_API_KEY), osm = OpenStreetMap/Photon (free, fair use), none = manual entry only.
        'provider' => env('ADDRESS_AUTOCOMPLETE', env('GOOGLE_MAPS_API_KEY') ? 'google' : 'osm'),
        'google_key' => env('GOOGLE_MAPS_API_KEY'),   // BROWSER key — restrict to your domain in Google Cloud
        'osm_url' => env('OSM_AUTOCOMPLETE_URL', 'https://photon.komoot.io/api/'),
    ],

    'maps' => [
        // Any Leaflet-compatible XYZ tile URL. The default is the public OSM server,
        // which has a strict usage policy; use a commercial tile provider in production.
        'tile_url' => env('MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'attribution' => env('MAP_TILE_ATTRIBUTION', '&copy; OpenStreetMap contributors'),
    ],

    'uploads' => [
        'max_kb' => (int) env('UPLOAD_MAX_KB', 5120),
        'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
    ],

    'defaults' => [
        'business_name' => env('APP_NAME', 'Courier'),
        'support_email' => env('SUPPORT_EMAIL', 'support@example.com'),
        'support_phone' => env('SUPPORT_PHONE', ''),
        'office_address' => '',
        'whatsapp_number' => env('WHATSAPP_NUMBER', ''),
        'tracking_prefix' => 'CX',
        'default_currency' => 'USD',
        'currencies' => ['USD'],
        'timezone' => 'America/New_York',
        'default_country' => 'US',
        'priority_countries' => ['US', 'CA', 'MX', 'GB'],
        'measurement_system' => 'imperial',
        'guest_booking_enabled' => true,
        'cod_enabled' => false,
        'quote_validity_minutes' => 30,
        'proof_require_signature' => false,
        'proof_require_photo' => false,
        'proof_require_code' => false,
        'customer_map_enabled' => false,
        'location_retention_days' => 30,
        'location_stale_minutes' => 5,
        'location_interval_seconds' => 30,
        'customer_cancel_statuses' => ['draft', 'pending_payment', 'booked', 'pickup_scheduled'],
        'sms_enabled' => false,
        'email_enabled' => true,
    ],
];
