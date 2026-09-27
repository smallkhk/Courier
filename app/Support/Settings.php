<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Administrator-editable business settings, falling back to config('courier.defaults').
 */
class Settings
{
    /** Keys administrators may edit, with their input type. */
    public const EDITABLE = [
        'business_name' => ['text', 'Business name'],
        'support_email' => ['email', 'Support email'],
        'support_phone' => ['text', 'Support phone'],
        'office_address' => ['text', 'Head office address'],
        'whatsapp_number' => ['text', 'WhatsApp number for the chat button (international format, e.g. 2348030000000; blank hides it)'],
        'tracking_prefix' => ['text', 'Tracking number prefix (2–4 letters)'],
        'default_currency' => ['text', 'Default currency (ISO 4217)'],
        'currencies' => ['list', 'Supported currencies (comma separated)'],
        'timezone' => ['text', 'Display timezone (e.g. America/New_York)'],
        'default_country' => ['text', 'Home country (ISO code, e.g. US) — default for addresses and phone numbers'],
        'priority_countries' => ['list', 'Countries listed first in pickers (comma separated ISO codes)'],
        'measurement_system' => ['text', 'Units shown to customers: imperial (lb/in) or metric (kg/cm)'],
        'guest_booking_enabled' => ['bool', 'Allow guest booking'],
        'cod_enabled' => ['bool', 'Offer cash on delivery (requires COD procedure)'],
        'quote_validity_minutes' => ['int', 'Quote validity (minutes)'],
        'proof_require_signature' => ['bool', 'Require signature for delivery'],
        'proof_require_photo' => ['bool', 'Require photo for delivery'],
        'proof_require_code' => ['bool', 'Require recipient delivery code'],
        'customer_map_enabled' => ['bool', 'Show approximate rider location to customers when out for delivery'],
        'location_retention_days' => ['int', 'Rider location history retention (days)'],
        'location_stale_minutes' => ['int', 'Mark rider location stale after (minutes)'],
        'location_interval_seconds' => ['int', 'Rider location update interval (seconds)'],
        'email_enabled' => ['bool', 'Send email notifications'],
        'sms_enabled' => ['bool', 'Send SMS notifications'],
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        $defaults = config('courier.defaults', []);
        try {
            $stored = Cache::remember('courier.settings', 300, fn () => Schema::hasTable('settings')
                ? Setting::query()->pluck('value', 'key')->all()
                : []);
        } catch (\Throwable) {
            $stored = [];
        }

        return array_merge($defaults, $stored);
    }

    public static function set(string $key, mixed $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('courier.settings');
    }

    public static function currency(): string
    {
        return strtoupper((string) self::get('default_currency', 'USD'));
    }
}
