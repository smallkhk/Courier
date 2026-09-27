<?php

namespace App\Support;

use libphonenumber\PhoneNumberUtil;
use Locale;

/** Every country/territory (ISO 3166-1 alpha-2) with English names and calling codes. */
class Countries
{
    /** @return array<string, string> code => name, sorted by name */
    public static function all(): array
    {
        static $list = null;
        if ($list === null) {
            $list = [];
            foreach (PhoneNumberUtil::getInstance()->getSupportedRegions() as $code) {
                $list[$code] = self::name($code);
            }
            asort($list, SORT_NATURAL | SORT_FLAG_CASE);
        }

        return $list;
    }

    /** Countries list with the business's main markets first. */
    public static function options(): array
    {
        $all = self::all();
        $top = [];
        foreach ((array) Settings::get('priority_countries', ['US']) as $c) {
            if (isset($all[$c])) {
                $top[$c] = $all[$c];
            }
        }

        return $top + $all;
    }

    public static function name(?string $code): string
    {
        if (! $code) {
            return '';
        }
        $name = class_exists(Locale::class) ? Locale::getDisplayRegion('-'.$code, 'en') : $code;

        return $name && $name !== $code ? $name : strtoupper($code);
    }

    public static function valid(?string $code): bool
    {
        return $code !== null && array_key_exists(strtoupper($code), self::all());
    }

    public static function flag(?string $code): string
    {
        if (! $code || strlen($code) !== 2) {
            return '';
        }
        $code = strtoupper($code);

        return mb_chr(0x1F1E6 + ord($code[0]) - 65).mb_chr(0x1F1E6 + ord($code[1]) - 65);
    }
}
