<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Owner/staff view of a shipment. Internal notes only for staff. */
class ShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $staff = $request->user()?->isStaff();

        return [
            'tracking_number' => $this->tracking_number,
            'tracking_url' => $this->publicTrackingUrl(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_changed_at' => $this->status_changed_at?->toIso8601String(),
            'service' => $this->whenLoaded('service', fn () => ['code' => $this->service->code, 'name' => $this->service->name]),
            'sender' => ['name' => $this->sender_name, 'phone' => $this->sender_phone, 'email' => $this->sender_email, 'address' => $this->pickup_address, 'city' => $this->pickup_city, 'state' => $this->pickup_state],
            'recipient' => ['name' => $this->recipient_name, 'phone' => $this->recipient_phone, 'email' => $this->recipient_email, 'address' => $this->delivery_address, 'city' => $this->delivery_city, 'state' => $this->delivery_state],
            'package' => ['description' => $this->package_description, 'category' => $this->package_category, 'parcel_count' => $this->parcel_count, 'chargeable_weight_kg' => (float) $this->chargeable_weight_kg, 'declared_value' => $this->declared_value, 'insured' => $this->insured],
            'payment_method' => $this->payment_method,
            'price' => ['subtotal' => $this->subtotal, 'tax' => $this->tax, 'total' => $this->total, 'currency' => $this->currency, 'breakdown' => $this->price_breakdown['lines'] ?? []],
            'estimated_delivery' => $this->estimated_delivery_to ? ['from' => $this->estimated_delivery_from?->toDateString(), 'to' => $this->estimated_delivery_to->toDateString(), 'is_estimate' => true] : null,
            'events' => $this->whenLoaded('events', fn () => $this->events->map(fn ($e) => array_filter([
                'status' => $e->status->value,
                'occurred_at' => $e->occurred_at->toIso8601String(),
                'location' => $e->location,
                'description' => $e->public_description,
                'source' => $e->source,
                'internal_note' => $staff ? $e->internal_note : null,
            ], fn ($v) => $v !== null))),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
