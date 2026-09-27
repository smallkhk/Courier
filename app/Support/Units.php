<?php

namespace App\Support;

/** Weight/dimension conversion. Parcels are stored in kg and cm; people may enter lb/in. */
final class Units
{
    public const LB_TO_KG = 0.45359237;

    public const IN_TO_CM = 2.54;

    public static function system(): string
    {
        return Settings::get('measurement_system', 'imperial') === 'metric' ? 'metric' : 'imperial';
    }

    public static function toKg(float|string|null $v, string $system): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }

        return $system === 'imperial' ? round((float) $v * self::LB_TO_KG, 4) : (float) $v;
    }

    public static function toCm(float|string|null $v, string $system): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }

        return $system === 'imperial' ? round((float) $v * self::IN_TO_CM, 3) : (float) $v;
    }

    /** "2.65 lb" or "1.2 kg" in the site's display system. */
    public static function weight(float|string|null $kg, ?string $system = null): string
    {
        $kg = (float) $kg;
        if (($system ?? self::system()) === 'imperial') {
            return rtrim(rtrim(number_format($kg / self::LB_TO_KG, 2), '0'), '.').' lb';
        }

        return rtrim(rtrim(number_format($kg, 2), '0'), '.').' kg';
    }

    public static function weightLabel(string $system): string
    {
        return $system === 'imperial' ? 'lb' : 'kg';
    }

    public static function lengthLabel(string $system): string
    {
        return $system === 'imperial' ? 'in' : 'cm';
    }
}
