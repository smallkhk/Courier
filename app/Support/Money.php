<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Decimal-safe money helpers. All arithmetic is done on integer minor units
 * (kobo, cents); values cross the DB boundary as 2-decimal strings.
 */
final class Money
{
    public static function toMinor(string|int|float|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }
        if (is_int($amount)) {
            return $amount * 100;
        }
        $s = is_float($amount) ? number_format($amount, 2, '.', '') : trim($amount);
        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,}))?$/', $s, $m)) {
            throw new InvalidArgumentException("Invalid money amount: {$s}");
        }
        $frac = (int) substr(str_pad($m[3] ?? '', 3, '0'), 0, 3);
        $minor = ((int) $m[2]) * 100 + intdiv($frac, 10) + ($frac % 10 >= 5 ? 1 : 0);

        return $m[1] === '-' ? -$minor : $minor;
    }

    public static function fromMinor(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $minor = abs($minor);

        return $sign.intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Apply a percentage (e.g. "7.5") to a minor amount, rounding half up. */
    public static function percentOf(int $minor, string|float|int $percent): int
    {
        // percent with 3 decimals -> integer thousandths of a percent
        $milli = (int) round(((float) (string) $percent) * 1000);

        return intdiv($minor * $milli + 50000, 100000);
    }

    public static function format(string|int|float|null $amount, string $currency): string
    {
        $minor = is_int($amount) ? $amount * 100 : self::toMinor($amount);
        $symbols = ['NGN' => '₦', 'USD' => '$', 'GBP' => '£', 'EUR' => '€', 'GHS' => 'GH₵', 'KES' => 'KSh '];
        $prefix = $symbols[strtoupper($currency)] ?? strtoupper($currency).' ';

        return $prefix.number_format($minor / 100, 2);
    }
}
