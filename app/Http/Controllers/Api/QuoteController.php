<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BookingController;
use App\Http\Controllers\Controller;
use App\Services\PricingService;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    public function store(Request $request, PricingService $pricing)
    {
        $data = $request->validate([
            'service_id' => 'required|integer|exists:services,id',
            'origin_zone_id' => 'required|integer|exists:service_zones,id',
            'destination_zone_id' => 'required|integer|exists:service_zones,id',
            'parcels' => 'required|array|min:1|max:20',
            'parcels.*.weight_kg' => 'required|numeric|min:0.01|max:1000',
            'parcels.*.length_cm' => 'nullable|numeric|min:1|max:500',
            'parcels.*.width_cm' => 'nullable|numeric|min:1|max:500',
            'parcels.*.height_cm' => 'nullable|numeric|min:1|max:500',
            'declared_value' => 'nullable|numeric|min:0',
            'insured' => 'nullable|boolean',
            'pickup_requested' => 'nullable|boolean',
        ]);
        $quote = $pricing->quote(BookingController::quoteInput($data), $request->user()?->id, BookingController::bookingBusiness($request));

        return response()->json([
            'id' => $quote->id,
            'total' => $quote->total,
            'currency' => $quote->currency,
            'breakdown' => $quote->breakdown['lines'],
            'chargeable_weight_kg' => $quote->breakdown['chargeable_weight_kg'],
            'expires_at' => $quote->expires_at->toIso8601String(),
        ], 201);
    }
}
