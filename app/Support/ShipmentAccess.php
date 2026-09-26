<?php

namespace App\Support;

use App\Models\Shipment;
use Illuminate\Http\Request;

/**
 * Owner-level access to a shipment page (checkout, confirmation, receipt).
 * Signed-in users: must be allowed by Shipment::visibleTo().
 * Guests: must hold the guest token (from the booking session or the ?token= link we emailed).
 */
class ShipmentAccess
{
    public static function rememberGuest(Request $request, Shipment $shipment, string $token): void
    {
        $tokens = $request->session()->get('guest_shipments', []);
        $tokens[$shipment->id] = $token;
        $request->session()->put('guest_shipments', array_slice($tokens, -20, null, true));
    }

    public static function guestToken(Request $request, Shipment $shipment): ?string
    {
        return $request->query('token') ?: ($request->session()->get('guest_shipments', [])[$shipment->id] ?? null);
    }

    public static function canManage(Request $request, Shipment $shipment): bool
    {
        if ($user = $request->user()) {
            if (Shipment::visibleTo($user)->whereKey($shipment->id)->exists() && ! $user->isRole('rider')) {
                return true;
            }
        }
        $token = self::guestToken($request, $shipment);
        if ($token && $shipment->guest_token_hash && hash_equals($shipment->guest_token_hash, hash('sha256', $token))) {
            self::rememberGuest($request, $shipment, $token);

            return true;
        }

        return false;
    }

    public static function authorize(Request $request, Shipment $shipment): void
    {
        if (! self::canManage($request, $shipment)) {
            abort(404);
        }
    }
}
