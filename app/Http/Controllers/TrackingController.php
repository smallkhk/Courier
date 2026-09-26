<?php

namespace App\Http\Controllers;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Services\LocationService;
use App\Support\Settings;
use App\Support\TrackingNumber;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function form()
    {
        return view('public.track');
    }

    public function lookup(Request $request)
    {
        $request->validate(['tracking_number' => 'required|string|max:40']);
        $tn = TrackingNumber::normalize($request->string('tracking_number'));

        return redirect()->route('track.show', ['tracking' => $tn]);
    }

    public function show(Request $request, string $tracking)
    {
        $tn = TrackingNumber::normalize($tracking);
        // Malformed numbers never touch the database; unknown and malformed look identical.
        $shipment = TrackingNumber::isWellFormed($tn)
            ? Shipment::with(['events', 'service'])->where('tracking_number', $tn)->first()
            : null;

        if (! $shipment || $shipment->status === ShipmentStatus::Draft) {
            return response()->view('public.track', ['notFound' => $tn], 404);
        }

        $verified = in_array($shipment->id, $request->session()->get('tracking_verified', []), true);

        return view('public.track-result', [
            'shipment' => $shipment,
            'verified' => $verified,
            'approx' => $verified ? $this->approximateLocation($shipment) : null,
        ]);
    }

    /** Optional second factor (last 4 digits of recipient phone) to reveal more detail. */
    public function verify(Request $request, string $tracking)
    {
        $request->validate(['phone_last4' => 'required|digits:4']);
        $shipment = Shipment::where('tracking_number', TrackingNumber::normalize($tracking))->firstOrFail();
        $digits = preg_replace('/\D/', '', $shipment->recipient_phone);
        if (! hash_equals(substr($digits, -4), (string) $request->input('phone_last4'))) {
            return back()->withErrors(['phone_last4' => 'Those digits do not match our records.']);
        }
        $ids = $request->session()->get('tracking_verified', []);
        $ids[] = $shipment->id;
        $request->session()->put('tracking_verified', array_values(array_unique(array_slice($ids, -20))));

        return redirect()->route('track.show', $shipment->tracking_number)->with('success', 'Verified. Additional delivery details are now shown.');
    }

    /** Coarse (~1 km) rider position, only when enabled and out for delivery, never labelled live if stale. */
    private function approximateLocation(Shipment $shipment): ?array
    {
        if (! Settings::get('customer_map_enabled') || $shipment->status !== ShipmentStatus::OutForDelivery) {
            return null;
        }
        $profile = $shipment->activeAssignment?->rider?->riderProfile;
        if (! $profile || ! $profile->last_location_at || $profile->last_lat === null) {
            return null;
        }
        if ($profile->last_location_at->lt(now()->subHour())) {
            return null;
        }

        return [
            'lat' => LocationService::coarsen($profile->last_lat),
            'lng' => LocationService::coarsen($profile->last_lng),
            'at' => $profile->last_location_at,
            'fresh' => $profile->locationFreshness() === 'live',
        ];
    }
}
