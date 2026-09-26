<?php

namespace App\Services;

use App\Enums\ShipmentStatus;
use App\Models\Business;
use App\Models\CodCollection;
use App\Models\Quote;
use App\Models\Service;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Support\Settings;
use App\Support\TrackingNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns a valid, unexpired quote plus contact details into a shipment.
 * The price always comes from the stored quote (computed server-side).
 */
class BookingService
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * @param  array<string, mixed>  $details  validated sender/recipient/package fields
     * @return array{shipment: Shipment, guest_token: ?string, created: bool}
     */
    public function create(array $details, Quote $quote, ?User $user, ?Business $business, string $paymentMethod, string $idempotencyKey): array
    {
        if ($existing = Shipment::where('idempotency_key', $idempotencyKey)->first()) {
            // Same submission replayed (double click, refresh, network retry).
            if ($existing->user_id !== $user?->id) {
                throw ValidationException::withMessages(['booking' => 'This booking request was already used.']);
            }

            return ['shipment' => $existing, 'guest_token' => null, 'created' => false];
        }

        if ($quote->isExpired()) {
            throw ValidationException::withMessages(['quote' => 'Your quote has expired. Please review the updated price.']);
        }
        if ($quote->user_id !== null && $quote->user_id !== $user?->id) {
            throw ValidationException::withMessages(['quote' => 'This quote belongs to another account.']);
        }
        if ($quote->business_id !== null && $quote->business_id !== $business?->id) {
            throw ValidationException::withMessages(['quote' => 'This quote belongs to another account.']);
        }

        $paymentMethod = $this->resolvePaymentMethod($paymentMethod, $business);
        if (! $user && ! Settings::get('guest_booking_enabled')) {
            throw ValidationException::withMessages(['booking' => 'Please sign in to book a shipment.']);
        }

        $inputs = $quote->inputs;
        $breakdown = $quote->breakdown;
        $service = Service::findOrFail($quote->service_id);
        $guestToken = $user ? null : Str::random(40);
        $deliveryCode = Settings::get('proof_require_code') ? (string) random_int(100000, 999999) : null;

        $initial = match ($paymentMethod) {
            'online' => ShipmentStatus::PendingPayment,
            default => ShipmentStatus::Booked,
        };

        $shipment = null;
        for ($attempt = 0; $attempt < 5 && ! $shipment; $attempt++) {
            try {
                $shipment = DB::transaction(function () use ($details, $quote, $user, $business, $paymentMethod, $idempotencyKey, $inputs, $breakdown, $service, $guestToken, $deliveryCode, $initial) {
                    $now = now();
                    $shipment = Shipment::create([
                        'tracking_number' => TrackingNumber::generate(),
                        'user_id' => $user?->id,
                        'business_id' => $business?->id,
                        'created_by' => $user?->id,
                        'quote_id' => $quote->id,
                        'service_id' => $service->id,
                        'origin_zone_id' => $quote->origin_zone_id,
                        'destination_zone_id' => $quote->destination_zone_id,
                        'sender_name' => $details['sender_name'],
                        'sender_phone' => $details['sender_phone'],
                        'sender_email' => $details['sender_email'] ?? null,
                        'pickup_address' => $details['pickup_address'],
                        'pickup_city' => $details['pickup_city'],
                        'pickup_state' => $details['pickup_state'],
                        'recipient_name' => $details['recipient_name'],
                        'recipient_phone' => $details['recipient_phone'],
                        'recipient_email' => $details['recipient_email'] ?? null,
                        'delivery_address' => $details['delivery_address'],
                        'delivery_city' => $details['delivery_city'],
                        'delivery_state' => $details['delivery_state'],
                        'pickup_instructions' => $details['pickup_instructions'] ?? null,
                        'delivery_instructions' => $details['delivery_instructions'] ?? null,
                        'package_description' => $details['package_description'],
                        'package_category' => $details['package_category'],
                        'parcel_count' => $breakdown['parcel_count'],
                        'chargeable_weight_kg' => $breakdown['chargeable_weight_kg'],
                        'declared_value' => $inputs['declared_value'] ?? 0,
                        'insured' => (bool) ($inputs['insured'] ?? false),
                        'special_handling' => $details['special_handling'] ?? [],
                        'pickup_requested' => (bool) ($inputs['pickup_requested'] ?? true),
                        'pickup_date' => $details['pickup_date'] ?? null,
                        'pickup_window' => $details['pickup_window'] ?? null,
                        'payment_method' => $paymentMethod,
                        'price_breakdown' => $breakdown,
                        'subtotal' => $breakdown['subtotal'],
                        'tax' => $breakdown['tax'],
                        'total' => $breakdown['total'],
                        'currency' => $quote->currency,
                        'cod_amount' => $paymentMethod === 'cod' ? $breakdown['total'] : 0,
                        'status' => $initial,
                        'status_changed_at' => $now,
                        'estimated_delivery_from' => $this->estimate($service, $details['pickup_date'] ?? null, 'min'),
                        'estimated_delivery_to' => $this->estimate($service, $details['pickup_date'] ?? null, 'max'),
                        'guest_token_hash' => $guestToken ? hash('sha256', $guestToken) : null,
                        'delivery_code_hash' => $deliveryCode ? Hash::make($deliveryCode) : null,
                        'idempotency_key' => $idempotencyKey,
                        'terms_accepted_at' => $now,
                        'booked_at' => $initial === ShipmentStatus::Booked ? $now : null,
                    ]);

                    foreach ($inputs['parcels'] as $p) {
                        $shipment->items()->create([
                            'description' => $p['description'] ?? null,
                            'weight_kg' => $p['weight_kg'],
                            'length_cm' => $p['length_cm'] ?? null,
                            'width_cm' => $p['width_cm'] ?? null,
                            'height_cm' => $p['height_cm'] ?? null,
                        ]);
                    }

                    ShipmentEvent::create([
                        'shipment_id' => $shipment->id,
                        'status' => $initial,
                        'occurred_at' => $now,
                        'location' => $shipment->pickup_city,
                        'public_description' => $initial->publicDescription(),
                        'actor_id' => $user?->id,
                        'source' => $user?->isStaff() ? $user->role : 'customer',
                    ]);

                    if ($paymentMethod === 'cod') {
                        CodCollection::create(['shipment_id' => $shipment->id, 'amount_due' => $shipment->cod_amount, 'currency' => $shipment->currency]);
                    }

                    return $shipment;
                });
            } catch (UniqueConstraintViolationException $e) {
                if (Shipment::where('idempotency_key', $idempotencyKey)->exists()) {
                    return ['shipment' => Shipment::where('idempotency_key', $idempotencyKey)->first(), 'guest_token' => null, 'created' => false];
                }
                // Tracking number collision: retry with a fresh number.
                $shipment = null;
            }
        }

        if (! $shipment) {
            throw new \RuntimeException('Could not allocate a unique tracking number.');
        }

        $this->notifications->forShipment($shipment, 'booking_created');
        if ($deliveryCode) {
            $this->notifications->deliveryCode($shipment, $deliveryCode);
        }

        return ['shipment' => $shipment, 'guest_token' => $guestToken, 'created' => true];
    }

    public function resolvePaymentMethod(string $requested, ?Business $business): string
    {
        if ($business && $business->isApproved() && $business->billsByInvoice()) {
            return 'invoice';
        }
        if ($requested === 'cod') {
            if (! Settings::get('cod_enabled')) {
                throw ValidationException::withMessages(['payment_method' => 'Cash on delivery is not available.']);
            }

            return 'cod';
        }

        return 'online';
    }

    /** Estimate only when the service has an owner-configured transit commitment. */
    private function estimate(Service $service, ?string $pickupDate, string $which): ?string
    {
        if (! $service->hasEstimate()) {
            return null;
        }
        $start = $pickupDate ? Carbon::parse($pickupDate) : now();
        $days = $which === 'min' ? $service->transit_days_min : $service->transit_days_max;

        return $start->copy()->addWeekdays($days)->toDateString();
    }
}
