<?php

namespace App\Support;

/** Subdivision lists for countries where a dropdown is expected; other countries use free text. */
class Regions
{
    public const US = [
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California', 'CO' => 'Colorado',
        'CT' => 'Connecticut', 'DE' => 'Delaware', 'DC' => 'District of Columbia', 'FL' => 'Florida', 'GA' => 'Georgia',
        'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
        'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland', 'MA' => 'Massachusetts',
        'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana',
        'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico',
        'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
        'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina', 'SD' => 'South Dakota',
        'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington',
        'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming', 'PR' => 'Puerto Rico', 'GU' => 'Guam',
        'VI' => 'U.S. Virgin Islands', 'AS' => 'American Samoa', 'MP' => 'Northern Mariana Islands',
        'AA' => 'Armed Forces Americas', 'AE' => 'Armed Forces Europe', 'AP' => 'Armed Forces Pacific',
    ];

    public const CA = [
        'AB' => 'Alberta', 'BC' => 'British Columbia', 'MB' => 'Manitoba', 'NB' => 'New Brunswick', 'NL' => 'Newfoundland and Labrador',
        'NS' => 'Nova Scotia', 'NT' => 'Northwest Territories', 'NU' => 'Nunavut', 'ON' => 'Ontario', 'PE' => 'Prince Edward Island',
        'QC' => 'Quebec', 'SK' => 'Saskatchewan', 'YT' => 'Yukon',
    ];

    /** @return array<string,string>|null */
    public static function for(?string $country): ?array
    {
        return match (strtoupper((string) $country)) {
            'US' => self::US,
            'CA' => self::CA,
            default => null,
        };
    }

    /** Normalise "New York" / "ny" / "NY" to "NY" where a list exists; otherwise trimmed input. */
    public static function normalize(?string $country, ?string $region): ?string
    {
        $region = trim((string) $region);
        if ($region === '') {
            return null;
        }
        $list = self::for($country);
        if (! $list) {
            return $region;
        }
        $upper = strtoupper($region);
        if (isset($list[$upper])) {
            return $upper;
        }
        $found = array_search(mb_strtolower($region), array_map('mb_strtolower', $list), true);

        return $found !== false ? $found : $region;
    }

    /** Postal-code format check for the countries where a wrong code most often breaks delivery. */
    public static function postalValid(?string $country, ?string $postal): bool
    {
        $postal = strtoupper(trim((string) $postal));
        $patterns = [
            'US' => '/^\d{5}(-\d{4})?$/',
            'CA' => '/^[A-Z]\d[A-Z] ?\d[A-Z]\d$/',
            'GB' => '/^[A-Z]{1,2}\d[A-Z\d]? ?\d[A-Z]{2}$/',
            'AU' => '/^\d{4}$/',
            'DE' => '/^\d{5}$/',
            'FR' => '/^\d{5}$/',
            'MX' => '/^\d{5}$/',
        ];
        $p = $patterns[strtoupper((string) $country)] ?? null;

        return $p === null || preg_match($p, $postal) === 1;
    }

    /** Countries where a postal code is required for delivery. */
    public static function postalRequired(?string $country): bool
    {
        return in_array(strtoupper((string) $country), ['US', 'CA', 'GB', 'AU', 'DE', 'FR', 'MX', 'IT', 'ES', 'NL', 'JP', 'CN', 'IN', 'BR'], true);
    }
}
