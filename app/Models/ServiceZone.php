<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ServiceZone extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['cities' => 'array', 'is_remote' => 'boolean', 'pickup_enabled' => 'boolean', 'delivery_enabled' => 'boolean', 'active' => 'boolean'];
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_zone');
    }

    public function coversCity(string $city): bool
    {
        $cities = array_map(fn ($c) => mb_strtolower(trim($c)), $this->cities ?? []);

        return $cities === [] || in_array(mb_strtolower(trim($city)), $cities, true);
    }
}
