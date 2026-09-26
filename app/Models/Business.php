<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'business_members')->withPivot('role', 'id')->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(BusinessMember::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function billsByInvoice(): bool
    {
        return $this->payment_terms === 'invoice';
    }
}
