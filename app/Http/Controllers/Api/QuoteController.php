<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BookingController;
use App\Http\Controllers\Controller;
use App\Services\PricingService;
use App\Services\ZoneResolver;
use App\Support\Units;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class QuoteController extends Controller
{
    public function store(Request $request, PricingService $pricing, ZoneResolver $zones)
    {
        $loc = fn ($p) => [
            "$p.country" => 'required|string|size:2', "$p.region" => 'nullable|string|max:100',
            "$p.postal_code" => 'nullable|string|max:20', "$p.city" => 'nullable|string|max:100',
        ];
        $data = $request->validate($loc('origin') + $loc('destination') + [
            'service_id' => 'required|integer|exists:services,id',
            'units' => 'nullable|in:imperial,metric',
            'parcels' => 'required|array|min:1|max:20',
            'parcels.*.weight' => 'required|numeric|min:0.01|max:5000',
            'parcels.*.length' => 'nullable|numeric|min:0.1|max:1000',
            'parcels.*.width' => 'nullable|numeric|min:0.1|max:1000',
            'parcels.*.height' => 'nullable|numeric|min:0.1|max:1000',
            'declared_value' => 'nullable|numeric|min:0',
            'insured' => 'nullable|boolean',
            'pickup_requested' => 'nullable|boolean',
        ]);
        $o = $data['origin'];
        $t = $data['destination'];
        $origin = $zones->resolve($o['country'], $o['region'] ?? null, $o['postal_code'] ?? null, $o['city'] ?? null);
        $dest = $zones->resolve($t['country'], $t['region'] ?? null, $t['postal_code'] ?? null, $t['city'] ?? null);
        if (! $origin || ! $dest) {
            throw ValidationException::withMessages(array_filter([
                'origin' => $origin ? null : 'Pickup location is not served.',
                'destination' => $dest ? null : 'Destination is not served.',
            ]));
        }
        $system = $data['units'] ?? Units::system();
        $input = BookingController::quoteInput([
            'service_id' => $data['service_id'],
            'origin_zone_id' => $origin->id,
            'destination_zone_id' => $dest->id,
            'parcels' => array_map(fn ($p) => [
                'weight_kg' => Units::toKg($p['weight'], $system),
                'length_cm' => Units::toCm($p['length'] ?? null, $system),
                'width_cm' => Units::toCm($p['width'] ?? null, $system),
                'height_cm' => Units::toCm($p['height'] ?? null, $system),
            ], $data['parcels']),
            'declared_value' => $data['declared_value'] ?? 0,
            'insured' => $data['insured'] ?? false,
            'pickup_requested' => $data['pickup_requested'] ?? false,
        ]);
        $quote = $pricing->quote($input, $request->user()?->id, BookingController::bookingBusiness($request));

        return response()->json([
            'id' => $quote->id,
            'total' => $quote->total,
            'currency' => $quote->currency,
            'breakdown' => $quote->breakdown['lines'],
            'chargeable_weight' => $quote->breakdown['chargeable_weight'],
            'weight_unit' => $quote->breakdown['weight_unit'],
            'origin_zone' => $origin->code,
            'destination_zone' => $dest->code,
            'expires_at' => $quote->expires_at->toIso8601String(),
        ], 201);
    }
}
