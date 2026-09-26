<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    protected $guarded = ['id'];

    public const CATEGORIES = [
        'delay' => 'Delayed delivery',
        'damaged' => 'Damaged parcel',
        'missing' => 'Missing parcel',
        'payment' => 'Payment issue',
        'dispute' => 'Delivery dispute',
        'claim' => 'Claim / compensation',
        'general' => 'General enquiry',
    ];

    public const STATUSES = [
        'open' => 'Open', 'in_progress' => 'In progress', 'awaiting_customer' => 'Awaiting your reply',
        'escalated' => 'Escalated', 'resolved' => 'Resolved', 'closed' => 'Closed',
    ];

    public const PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)->orderBy('id');
    }

    public function publicMessages(): HasMany
    {
        return $this->messages()->where('is_internal', false);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
