<?php

namespace App\Models;

use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderProfile extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean', 'on_duty' => 'boolean', 'location_sharing' => 'boolean',
            'on_duty_since' => 'datetime', 'location_consent_at' => 'datetime', 'last_location_at' => 'datetime',
            'last_lat' => 'float', 'last_lng' => 'float', 'last_accuracy_m' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class, 'service_zone_id');
    }

    /** live | stale | none — never call a stale fix "live". */
    public function locationFreshness(): string
    {
        if (! $this->last_location_at) {
            return 'none';
        }
        $staleAfter = (int) Settings::get('location_stale_minutes');

        return $this->location_sharing && $this->last_location_at->gt(now()->subMinutes($staleAfter)) ? 'live' : 'stale';
    }

    public function activeAssignmentCount(): int
    {
        return RiderAssignment::where('rider_id', $this->user_id)->whereIn('status', ['assigned', 'accepted'])->count();
    }
}
