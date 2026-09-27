<?php

namespace App\Http\Controllers\Api;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Support\TrackingNumber;

class TrackingController extends Controller
{
    /** Public tracking: limited, customer-safe fields only. */
    public function show(string $tracking)
    {
        $tn = TrackingNumber::normalize($tracking);
        $s = TrackingNumber::isWellFormed($tn) ? Shipment::with('events', 'service')->where('tracking_number', $tn)->first() : null;
        if (! $s || $s->status === ShipmentStatus::Draft) {
            return response()->json(['message' => 'Tracking number not found.'], 404);
        }

        return response()->json([
            'tracking_number' => $s->tracking_number,
            'status' => $s->status->value,
            'status_label' => $s->status->label(),
            'last_updated_at' => $s->status_changed_at?->toIso8601String(),
            'origin_area' => $s->area('pickup'),
            'origin_country' => $s->pickup_country,
            'destination_area' => $s->area('delivery'),
            'destination_country' => $s->delivery_country,
            'service' => $s->service->name,
            'estimated_delivery' => ($s->estimated_delivery_to && ! $s->status->isTerminal())
                ? ['from' => $s->estimated_delivery_from?->toDateString(), 'to' => $s->estimated_delivery_to->toDateString(), 'is_estimate' => true] : null,
            'events' => $s->events->map(fn ($e) => [
                'status' => $e->status->value,
                'status_label' => $e->status->label(),
                'occurred_at' => $e->occurred_at->toIso8601String(),
                'location' => $e->location,
                'description' => $e->public_description,
            ])->values(),
            'support_url' => route('support.contact', ['tracking' => $s->tracking_number]),
        ]);
    }
}
