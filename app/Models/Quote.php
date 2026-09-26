<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quote extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['inputs' => 'array', 'breakdown' => 'array', 'expires_at' => 'datetime'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
