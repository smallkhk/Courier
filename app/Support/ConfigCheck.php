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
            $p[] = ['error', 'PAYMENT_PROVIDER=sandbox is not allowed in production. Set PAYMENT_PROVIDER=paystack.'];
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
