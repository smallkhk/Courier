<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $guarded = ['id', 'user_id', 'business_id'];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class, 'service_zone_id');
    }

    public function oneLine(): string
    {
        return collect([$this->line1, $this->line2, $this->city, $this->state])->filter()->implode(', ');
    }
}
