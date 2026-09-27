<?php

namespace App\Models;

use App\Support\Countries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
