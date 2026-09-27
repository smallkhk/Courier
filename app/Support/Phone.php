<?php

namespace App\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/** International phone numbers (libphonenumber). Stored and sent in E.164, e.g. +12125550123. */
class Phone
{
    public static function toE164(?string $raw, ?string $defaultCountry = null): ?string
    {
        if (! $raw || trim($raw) === '') {
            return null;
        }
        $util = PhoneNumberUtil::getInstance();
        try {
            $n = $util->parse(trim($raw), strtoupper($defaultCountry ?: (string) Settings::get('default_country', 'US')));
        } catch (NumberParseException) {
            return null;
        }

        return $util->isValidNumber($n) ? $util->format($n, PhoneNumberFormat::E164) : null;
    }

    public static function isValid(?string $raw, ?string $country = null): bool
    {
        return self::toE164($raw, $country) !== null;
    }

    /** Human display: national format at home, international otherwise. */
    public static function display(?string $e164): string
    {
        if (! $e164) {
            return '';
        }
        $util = PhoneNumberUtil::getInstance();
        try {
            $n = $util->parse($e164, null);
        } catch (NumberParseException) {
            return $e164;
        }
        $home = strtoupper((string) Settings::get('default_country', 'US'));

        return $util->format($n, $util->getRegionCodeForNumber($n) === $home ? PhoneNumberFormat::NATIONAL : PhoneNumberFormat::INTERNATIONAL);
    }
}
