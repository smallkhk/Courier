<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderAssignment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime', 'responded_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['assigned', 'accepted'], true);
    }
}
