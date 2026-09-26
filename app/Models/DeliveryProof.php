<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryProof extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['signature_path', 'photo_path'];

    protected function casts(): array
    {
        return ['delivered_at' => 'datetime', 'photo_consent' => 'boolean', 'code_verified' => 'boolean'];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
