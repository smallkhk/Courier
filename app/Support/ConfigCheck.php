<?php

namespace App\Support;

class ConfigCheck
{
    /** @return list<array{0:string,1:string}> [level, message] */
    public static function problems(): array
    {
        $p = [];
        $prod = app()->environment('production');
        if (! config('app.key')) {
            $p[] = ['error', 'APP_KEY is not set. Run: php artisan key:generate'];
        }
        if ($prod && config('app.debug')) {
            $p[] = ['error', 'APP_DEBUG must be false in production.'];
        }
        if ($prod && ! str_starts_with((string) config('app.url'), 'https://')) {
            $p[] = ['error', 'APP_URL must use https:// in production.'];
        }
        $provider = config('courier.payments.provider');
        if ($prod && $provider === 'sandbox') {
            $p[] = ['error', 'PAYMENT_PROVIDER=sandbox is not allowed in production. Set PAYMENT_PROVIDER=stripe (or paystack).'];
        }
        if ($provider === 'stripe' && (! config('courier.payments.stripe.secret_key') || ! config('courier.payments.stripe.webhook_secret'))) {
            $p[] = ['error', 'STRIPE_SECRET_KEY and STRIPE_WEBHOOK_SECRET are required when PAYMENT_PROVIDER=stripe.'];
        }
        if ($prod && str_starts_with((string) config('courier.payments.stripe.secret_key'), 'sk_test_')) {
            $p[] = ['warn', 'A Stripe TEST secret key is configured in production.'];
        }
        if (config('courier.address_autocomplete.provider') === 'google' && ! config('courier.address_autocomplete.google_key')) {
            $p[] = ['warn', 'ADDRESS_AUTOCOMPLETE=google but GOOGLE_MAPS_API_KEY is empty — falling back to OpenStreetMap search.'];
        }
        if ($prod && config('courier.address_autocomplete.provider') === 'osm') {
            $p[] = ['warn', 'Address search uses the public OpenStreetMap/Photon service (fair use only). Set GOOGLE_MAPS_API_KEY for production volumes.'];
        }
        if ($provider === 'paystack' && (! config('courier.payments.paystack.secret_key') || ! config('courier.payments.paystack.public_key'))) {
            $p[] = ['error', 'PAYSTACK_SECRET_KEY / PAYSTACK_PUBLIC_KEY are required when PAYMENT_PROVIDER=paystack.'];
        }
        if ($prod && str_starts_with((string) config('courier.payments.paystack.secret_key'), 'sk_test_')) {
            $p[] = ['warn', 'A Paystack TEST secret key is configured in production.'];
        }
        if ($prod && in_array(config('mail.default'), ['log', 'array'], true)) {
            $p[] = ['warn', 'MAIL_MAILER is "'.config('mail.default').'" — emails are not being delivered.'];
        }
        if (config('courier.sms.driver') === 'twilio' && (! config('courier.sms.twilio.sid') || ! config('courier.sms.twilio.token') || (! config('courier.sms.twilio.from') && ! config('courier.sms.twilio.messaging_service_sid')))) {
            $p[] = ['error', 'TWILIO_SID, TWILIO_AUTH_TOKEN and TWILIO_FROM (or TWILIO_MESSAGING_SERVICE_SID) are required when SMS_DRIVER=twilio.'];
        }
        if (config('courier.sms.driver') === 'termii' && (! config('courier.sms.termii.api_key') || ! config('courier.sms.termii.sender_id'))) {
            $p[] = ['error', 'TERMII_API_KEY / TERMII_SENDER_ID are required when SMS_DRIVER=termii.'];
        }
        if ($prod && config('courier.sms.driver') === 'log' && Settings::get('sms_enabled')) {
            $p[] = ['warn', 'SMS notifications are enabled but SMS_DRIVER=log — nothing is being sent.'];
        }
        if ($prod && str_contains((string) config('courier.maps.tile_url'), 'tile.openstreetmap.org')) {
            $p[] = ['warn', 'Using the public OpenStreetMap tile server. Its usage policy forbids heavy use; configure MAP_TILE_URL.'];
        }

        return $p;
    }
}
