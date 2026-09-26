<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['next_attempt_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function maskedRecipient(): string
    {
        $r = $this->recipient;
        if (str_contains($r, '@')) {
            [$u, $d] = explode('@', $r, 2);

            return mb_substr($u, 0, 2).'***@'.$d;
        }

        return str_repeat('*', max(0, strlen($r) - 4)).substr($r, -4);
    }
}
