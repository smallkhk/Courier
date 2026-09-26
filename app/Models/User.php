<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    public const ROLES = ['customer', 'rider', 'dispatcher', 'admin'];

    protected $fillable = ['name', 'email', 'phone', 'password', 'role', 'status', 'notification_preferences', 'sms_consent_at'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'sms_consent_at' => 'datetime',
            'password' => 'hashed',
            'notification_preferences' => 'array',
        ];
    }

    public function isRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isStaff(): bool
    {
        return $this->isRole('dispatcher', 'admin');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function wantsNotification(string $channel): bool
    {
        $prefs = $this->notification_preferences ?? [];
        if ($channel === 'sms') {
            return $this->sms_consent_at !== null && ($prefs['sms'] ?? true);
        }

        return $prefs[$channel] ?? true;
    }

    public function riderProfile(): HasOne
    {
        return $this->hasOne(RiderProfile::class);
    }

    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'business_members')->withPivot('role')->withTimestamps();
    }

    public function membershipFor(?int $businessId): ?BusinessMember
    {
        if (! $businessId) {
            return null;
        }

        return BusinessMember::where('user_id', $this->id)->where('business_id', $businessId)->first();
    }

    /** The approved business this user currently acts for (first membership). */
    public function primaryMembership(): ?BusinessMember
    {
        return BusinessMember::with('business')->where('user_id', $this->id)->orderBy('id')->first();
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
