<?php

namespace App\Services;

use App\Models\ServiceZone;
use App\Support\Regions;
use Illuminate\Support\Collection;

/**
 * Works out which coverage zone an address belongs to — customers never pick a zone.
 * A zone matches when its country (or "any country") matches and every rule it defines
 * (regions, postal-code prefixes, cities) matches. The most specific match wins:
 * postal prefix (longest) > city > region > country > any.
 */
class ZoneResolver
{
    /** @var Collection<int, ServiceZone>|null */
    private ?Collection $zones = null;

    public function resolve(?string $country, ?string $region, ?string $postal, ?string $city, bool $activeOnly = true): ?ServiceZone
    {
        $country = strtoupper(trim((string) $country));
        $region = Regions::normalize($country, $region);
        $postalN = strtoupper(preg_replace('/\s+/', '', (string) $postal));
        $cityN = mb_strtolower(trim((string) $city));

        $best = null;
        $bestScore = -1;
        foreach ($this->zones($activeOnly) as $z) {
            if ($z->country_code && strtoupper($z->country_code) !== $country) {
                continue;
            }
            $score = $z->country_code ? 10 : 0;

            $prefixes = array_filter(array_map(fn ($p) => strtoupper(preg_replace('/\s+/', '', $p)), $z->postal_prefixes ?? []));
            if ($prefixes) {
                $hit = collect($prefixes)->filter(fn ($p) => $postalN !== '' && str_starts_with($postalN, $p))->max(fn ($p) => strlen($p));
                if (! $hit) {
                    continue;
                }
                $score += 1000 + $hit * 10;
            }
            $cities = array_map(fn ($c) => mb_strtolower(trim($c)), $z->cities ?? []);
            if ($cities) {
                if (! in_array($cityN, $cities, true)) {
                    continue;
                }
                $score += 500;
            }
            $regions = array_map(fn ($r) => Regions::normalize($country, $r), $z->regions ?? []);
            if ($regions) {
                if (! $region || ! in_array(mb_strtolower($region), array_map('mb_strtolower', $regions), true)) {
                    continue;
                }
                $score += 100;
            }
            if ($score > $bestScore) {
                $best = $z;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private function zones(bool $activeOnly): Collection
    {
        return $this->zones ??= ServiceZone::query()->when($activeOnly, fn ($q) => $q->where('active', true))->get();
    }
}
