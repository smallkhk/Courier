<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RiderAssignment;
use App\Services\DispatchService;
use App\Services\LocationService;
use App\Support\Audit;
use Illuminate\Http\Request;

class RiderController extends Controller
{
    public function assignments(Request $request)
    {
        $rows = RiderAssignment::with('shipment')->where('rider_id', $request->user()->id)->whereIn('status', ['assigned', 'accepted'])->latest('assigned_at')->get();

        return response()->json(['data' => $rows->map(fn ($a) => [
            'id' => $a->id, 'status' => $a->status, 'leg' => $a->leg, 'assigned_at' => $a->assigned_at->toIso8601String(),
            'shipment' => [
                'tracking_number' => $a->shipment->tracking_number, 'status' => $a->shipment->status->value,
                'pickup' => ['name' => $a->shipment->sender_name, 'phone' => $a->shipment->sender_phone, 'address' => "{$a->shipment->pickup_address}, {$a->shipment->pickup_city}", 'instructions' => $a->shipment->pickup_instructions],
                'dropoff' => ['name' => $a->shipment->recipient_name, 'phone' => $a->shipment->recipient_phone, 'address' => "{$a->shipment->delivery_address}, {$a->shipment->delivery_city}", 'instructions' => $a->shipment->delivery_instructions],
            ],
        ])]);
    }

    public function accept(Request $request, RiderAssignment $assignment, DispatchService $dispatch)
    {
        $dispatch->respond($assignment, $request->user(), true);

        return response()->json(['id' => $assignment->id, 'status' => 'accepted']);
    }

    public function location(Request $request, LocationService $locations)
    {
        $data = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0',
            'recorded_at' => 'nullable|date',
        ]);
        $loc = $locations->record($request->user(), (float) $data['lat'], (float) $data['lng'], isset($data['accuracy']) ? (float) $data['accuracy'] : null, $data['recorded_at'] ?? null);

        return response()->json(['received_at' => $loc->received_at->toIso8601String()], 201);
    }

    public function availability(Request $request)
    {
        $request->validate(['on_duty' => 'required|boolean']);
        $profile = $request->user()->riderProfile;
        abort_unless($profile && $profile->is_active, 403, 'Your rider profile is not active.');
        $on = $request->boolean('on_duty');
        $profile->forceFill([
            'on_duty' => $on,
            'on_duty_since' => $on ? now() : null,
            // Going off duty always stops location sharing.
            'location_sharing' => $on ? $profile->location_sharing : false,
        ])->save();
        Audit::log($on ? 'rider.on_duty' : 'rider.off_duty', $profile);

        return response()->json(['on_duty' => $profile->on_duty, 'location_sharing' => $profile->location_sharing]);
    }

    public function sharing(Request $request)
    {
        $request->validate(['sharing' => 'required|boolean', 'consent' => 'nullable|boolean']);
        $profile = $request->user()->riderProfile;
        abort_unless($profile && $profile->is_active, 403);
        $on = $request->boolean('sharing');
        if ($on && ! $profile->on_duty) {
            abort(422, 'Go on duty before sharing your location.');
        }
        if ($on && ! $profile->location_consent_at && ! $request->boolean('consent')) {
            abort(422, 'Location sharing requires your consent.');
        }
        $profile->forceFill([
            'location_sharing' => $on,
            'location_consent_at' => $profile->location_consent_at ?? ($on ? now() : null),
        ])->save();
        Audit::log($on ? 'rider.location_sharing_on' : 'rider.location_sharing_off', $profile);

        return response()->json(['location_sharing' => $profile->location_sharing]);
    }
}
