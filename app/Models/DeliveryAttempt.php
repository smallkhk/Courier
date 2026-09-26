<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryAttempt extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['evidence_path'];

    public const REASONS = [
        'recipient_unavailable' => 'Recipient unavailable',
        'address_issue' => 'Address could not be found / incorrect',
        'recipient_declined' => 'Recipient declined the parcel',
        'access_issue' => 'Could not access the location',
        'other' => 'Other',
    ];

    public const RESOLUTIONS = [
        'retry' => 'Schedule another attempt',
        'contact_recipient' => 'Contact recipient first',
        'hold' => 'Hold at hub',
        'return' => 'Return to sender',
    ];

    protected function casts(): array
    {
        return ['attempted_at' => 'datetime', 'resolved_at' => 'datetime', 'retry_on' => 'date'];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }
}
