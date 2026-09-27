<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use App\Support\Countries;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Shipment extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['guest_token_hash', 'delivery_code_hash', 'idempotency_key'];

    public const CATEGORIES = [
        'documents' => 'Documents',
        'electronics' => 'Electronics',
        'clothing' => 'Clothing & textiles',
        'food' => 'Packaged food (non-perishable)',
        'health' => 'Health & beauty',
        'household' => 'Household items',
        'parts' => 'Parts & tools',
        'other' => 'Other',
    ];

    public const HANDLING = [
        'fragile' => 'Fragile',
        'keep_upright' => 'Keep upright',
        'keep_dry' => 'Keep dry',
        'id_check' => 'Check recipient ID',
    ];

    public const PICKUP_WINDOWS = [
        'morning' => 'Morning (8am – 12pm)',
        'afternoon' => 'Afternoon (12pm – 4pm)',
        'evening' => 'Evening (4pm – 7pm)',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'special_handling' => 'array',
            'customs' => 'array',
            'pickup_lat' => 'float', 'pickup_lng' => 'float', 'delivery_lat' => 'float', 'delivery_lng' => 'float',
            'price_breakdown' => 'array',
            'insured' => 'boolean',
            'pickup_requested' => 'boolean',
            'pickup_date' => 'date',
            'estimated_delivery_from' => 'date',
            'estimated_delivery_to' => 'date',
            'status_changed_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'booked_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'tracking_number';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function originZone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class, 'origin_zone_id');
    }

    public function destinationZone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class, 'destination_zone_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RiderAssignment::class)->latest('id');
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(RiderAssignment::class)->whereIn('status', ['assigned', 'accepted'])->latestOfMany();
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(DeliveryAttempt::class)->latest('attempted_at');
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(DeliveryProof::class)->latest('id');
    }

    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'payment_shipment')->withPivot('amount');
    }

    public function codCollection(): HasOne
    {
        return $this->hasOne(CodCollection::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function isPaid(): bool
    {
        return $this->payments()->where('status', 'successful')->exists();
    }

    public function isPayable(): bool
    {
        return $this->status === ShipmentStatus::PendingPayment && $this->payment_method === 'online';
    }

    /** Scope: shipments a customer or business member may see. Business isolation lives here. */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->isStaff()) {
            return $q;
        }
        if ($user->isRole('rider')) {
            return $q->whereHas('assignments', fn ($a) => $a->where('rider_id', $user->id));
        }

        return $q->where(function ($w) use ($user) {
            $w->where(fn ($p) => $p->where('user_id', $user->id)->whereNull('business_id'));
            $memberships = BusinessMember::where('user_id', $user->id)->get();
            foreach ($memberships as $m) {
                if ($m->can('view_all')) {
                    $w->orWhere('business_id', $m->business_id);
                } else {
                    $w->orWhere(fn ($p) => $p->where('business_id', $m->business_id)->where('created_by', $user->id));
                }
            }
        });
    }

    public const CUSTOMS_CONTENTS = [
        'merchandise' => 'Merchandise (sold goods)',
        'gift' => 'Gift',
        'documents' => 'Documents',
        'sample' => 'Commercial sample',
        'return' => 'Returned goods',
        'personal' => 'Personal effects',
    ];

    public function isInternational(): bool
    {
        return strtoupper((string) $this->pickup_country) !== strtoupper((string) $this->delivery_country);
    }

    public function addressLine(string $side): string
    {
        return collect([$this->{$side.'_address'}, $this->{$side.'_address2'}, $this->{$side.'_city'},
            trim($this->{$side.'_region'}.' '.$this->{$side.'_postal_code'}), Countries::name($this->{$side.'_country'})])->filter()->implode(', ');
    }

    /** City-level area, safe for public display (no street address). */
    public function area(string $side): string
    {
        $c = $this->{$side.'_country'};

        return collect([$this->{$side.'_city'}, $this->{$side.'_region'}, strtoupper((string) $c) !== strtoupper((string) Settings::get('default_country')) ? Countries::name($c) : null])->filter()->implode(', ');
    }

    public function publicTrackingUrl(): string
    {
        return route('track.show', ['tracking' => $this->tracking_number]);
    }
}
