<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Service extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'max_weight_kg' => 'decimal:2'];
    }

    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(ServiceZone::class, 'service_zone');
    }

    public function hasEstimate(): bool
    {
        return $this->transit_days_min !== null && $this->transit_days_max !== null;
    }

    public function transitLabel(): ?string
    {
        if (! $this->hasEstimate()) {
            return null;
        }
        if ($this->transit_days_min === 0 && $this->transit_days_max === 0) {
            return 'Same business day (estimate)';
        }

        return $this->transit_days_min === $this->transit_days_max
            ? "{$this->transit_days_min} business day(s) (estimate)"
            : "{$this->transit_days_min}–{$this->transit_days_max} business days (estimate)";
    }
}
