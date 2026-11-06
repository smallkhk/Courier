<?php

namespace App\Models;

use App\Support\Countries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class ServiceZone extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['cities' => 'array', 'regions' => 'array', 'postal_prefixes' => 'array', 'is_remote' => 'boolean', 'pickup_enabled' => 'boolean', 'delivery_enabled' => 'boolean', 'active' => 'boolean'];
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_zone');
    }

    /** True when the zone covers a whole country (or the whole world) with no narrowing rules. */
    public function isWide(): bool
    {
        return ! $this->regions && ! $this->postal_prefixes && ! $this->cities;
    }

    /**
     * This zone followed by the wider zones it sits inside: its country-wide zone (same
     * country, no narrowing rules) and a worldwide zone (no country). State or city zones
     * inherit services and prices from these unless they are given their own.
     *
     * @return Collection<int, ServiceZone>
     */
    public function lineage(): Collection
    {
        $wider = static::query()->where('active', true)->whereKeyNot($this->id)
            ->where(fn ($q) => $q->whereNull('country_code')->when($this->country_code, fn ($q) => $q->orWhere('country_code', $this->country_code)))
            ->get()
            ->filter(fn (ServiceZone $z) => $z->isWide() && ($this->isWide() ? $z->country_code === null && $this->country_code !== null : true))
            ->sortBy(fn (ServiceZone $z) => $z->country_code === null ? 1 : 0)
            ->values();

        return collect([$this])->merge($wider);
    }

    /** Human summary of what this zone covers, e.g. "United States · NY, NJ · ZIP 100–104". */
    public function coverageSummary(): string
    {
        $parts = [$this->country_code ? Countries::name($this->country_code) : 'Any country'];
        if ($this->regions) {
            $parts[] = implode(', ', $this->regions);
        }
        if ($this->postal_prefixes) {
            $parts[] = 'Postal codes '.implode(', ', array_map(fn ($p) => $p.'…', $this->postal_prefixes));
        }
        if ($this->cities) {
            $parts[] = implode(', ', $this->cities);
        }

        return implode(' · ', $parts);
    }
}
