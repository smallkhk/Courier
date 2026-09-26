<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'paid_at' => 'datetime', 'verified_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function shipments(): BelongsToMany
    {
        return $this->belongsToMany(Shipment::class, 'payment_shipment')->withPivot('amount');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function isFinal(): bool
    {
        return in_array($this->status, ['successful', 'failed', 'cancelled', 'refunded'], true);
    }
}
