<?php

namespace App\Services;

use App\Models\RiderLocation;
use App\Models\User;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Accepts browser geolocation updates from on-duty riders who have consented.
 */
class LocationService
{
    /** Anything faster than this between two fixes is treated as implausible. */
    public const MAX_SPEED_KMH = 180;

    public const MAX_ACCURACY_M = 5000;

    public function record(User $rider, float $lat, float $lng, ?float $accuracy, ?string $recordedAt): RiderLocation
    {
        $profile = $rider->riderProfile;
        if (! $rider->isRole('rider') || ! $profile || ! $profile->is_active) {
            throw new AuthorizationException('Only active riders can share location.');
        }
        if (! $profile->on_duty || ! $profile->location_consent_at || ! $profile->location_sharing) {
            throw ValidationException::withMessages(['location' => 'Location sharing is only accepted while you are on duty and sharing is switched on.']);
        }
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 || ($lat == 0.0 && $lng == 0.0)) {
            throw ValidationException::withMessages(['location' => 'Invalid coordinates.']);
        }
        if ($accuracy !== null && ($accuracy < 0 || $accuracy > self::MAX_ACCURACY_M)) {
            throw ValidationException::withMessages(['accuracy' => 'Location accuracy is too low to use.']);
        }

        $at = $recordedAt ? Carbon::parse($recordedAt) : now();
        if ($at->gt(now()->addMinute()) || $at->lt(now()->subMinutes(10))) {
            $at = now(); // Device clocks drift; never trust a far-off timestamp.
        }

        if ($profile->last_location_at && $profile->last_lat !== null) {
            $hours = max(abs($at->diffInSeconds($profile->last_location_at)), 1) / 3600;
            $km = self::distanceKm($profile->last_lat, $profile->last_lng, $lat, $lng);
            if ($km / $hours > self::MAX_SPEED_KMH && $km > 1) {
                Audit::log('rider.location_rejected', $profile, ['reason' => 'implausible_speed', 'km' => round($km, 2)]);
                throw ValidationException::withMessages(['location' => 'Location update rejected as implausible.']);
            }
        }

        $loc = RiderLocation::create([
            'rider_id' => $rider->id,
            'lat' => $lat,
            'lng' => $lng,
            'accuracy_m' => $accuracy,
            'recorded_at' => $at,
            'received_at' => now(),
            'expires_at' => now()->addDays((int) Settings::get('location_retention_days', 30)),
        ]);
        $profile->forceFill(['last_lat' => $lat, 'last_lng' => $lng, 'last_accuracy_m' => $accuracy, 'last_location_at' => $at])->save();

        return $loc;
    }

    public function prune(): int
    {
        return RiderLocation::where('expires_at', '<', now())->delete();
    }

    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }

    /** Round to ~1 km so customers never see a rider's precise position. */
    public static function coarsen(float $v): float
    {
        return round($v, 2);
    }
}
