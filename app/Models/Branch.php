<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Branch extends Model
{
    protected $guarded = ['id'];

    public const DAYS = ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];

    protected function casts(): array
    {
        return ['opening_hours' => 'array', 'holiday_closures' => 'array', 'is_pickup_point' => 'boolean', 'active' => 'boolean', 'lat' => 'float', 'lng' => 'float'];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class, 'service_zone_id');
    }

    public function directionsUrl(): string
    {
        $dest = $this->lat && $this->lng ? "{$this->lat},{$this->lng}" : urlencode("{$this->address}, {$this->city}, {$this->state}");

        return "https://www.google.com/maps/dir/?api=1&destination={$dest}";
    }

    /** @return list<array{date:string,note:?string}> upcoming closures */
    public function upcomingClosures(): array
    {
        $today = now()->toDateString();

        return array_values(array_filter($this->holiday_closures ?? [], fn ($c) => ($c['date'] ?? '') >= $today));
    }
}
