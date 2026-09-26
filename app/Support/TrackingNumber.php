<?php

namespace App\Support;

/**
 * Unpredictable public tracking numbers: PREFIX + 12 Crockford base32 chars
 * (60 bits of CSPRNG entropy) + 1 position-weighted check character.
 * Never derived from database IDs. Uniqueness is enforced by a DB constraint;
 * callers retry on collision.
 */
final class TrackingNumber
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function generate(?string $prefix = null): string
    {
        $prefix = strtoupper(preg_replace('/[^A-Za-z]/', '', $prefix ?? (string) Settings::get('tracking_prefix', 'CX')));
        $bytes = random_bytes(8);
        $bits = '';
        foreach (str_split($bytes) as $b) {
            $bits .= str_pad(decbin(ord($b)), 8, '0', STR_PAD_LEFT);
        }
        $body = '';
        for ($i = 0; $i < 12; $i++) {
            $body .= self::ALPHABET[bindec(substr($bits, $i * 5, 5))];
        }

        return $prefix.$body.self::checkChar($body);
    }

    public static function normalize(string $input): string
    {
        $s = strtoupper(preg_replace('/[\s\-]/', '', $input));
        if (! preg_match('/^([A-Z]{2,4}?)([0-9A-Z]{13})$/', $s, $m)) {
            return $s;
        }

        // Crockford: O -> 0, I/L -> 1 in the random body (never in the prefix).
        return $m[1].strtr($m[2], ['O' => '0', 'I' => '1', 'L' => '1']);
    }

    /** Cheap format + checksum validation so obvious typos never hit the database. */
    public static function isWellFormed(string $tracking): bool
    {
        if (! preg_match('/^([A-Z]{2,4}?)([0-9A-HJKMNP-TV-Z]{12})([0-9A-HJKMNP-TV-Z])$/', $tracking, $m)) {
            return false;
        }

        return self::checkChar($m[2]) === $m[3];
    }

    private static function checkChar(string $body): string
    {
        $sum = 0;
        // Position-weighted sum modulo the prime 31: catches every single-character
        // substitution and adjacent transposition (except the pair 0<->Z, values 0 and 31).
        foreach (str_split($body) as $i => $c) {
            $sum += ($i + 1) * strpos(self::ALPHABET, $c);
        }

        return self::ALPHABET[$sum % 31];
    }
}
