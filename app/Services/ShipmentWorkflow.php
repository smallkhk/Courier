<?php

namespace App\Services;

use App\Enums\ShipmentStatus;
use App\Models\DeliveryProof;
use App\Models\RiderAssignment;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Support\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only code path that changes a shipment's status. Enforces the state machine,
 * the acting role, rider ownership and proof-of-delivery rules, and writes the
 * append-only event.
 */
class ShipmentWorkflow
{
    public function __construct(private NotificationService $notifications) {}

    /** Map a user to the actor role used by the state machine. */
    public static function roleFor(?User $user): string
    {
        if (! $user) {
            return 'customer';
        }

        return match ($user->role) {
            'admin' => 'admin',
            'dispatcher' => 'dispatcher',
            'rider' => 'rider',
            default => 'customer',
        };
    }

    /**
     * @param  array{public_description?:?string, internal_note?:?string, location?:?string, proof?:?DeliveryProof, role?:string, source?:string, occurred_at?:mixed}  $opts
     */
    public function transition(Shipment $shipment, ShipmentStatus $to, ?User $actor, array $opts = []): ShipmentEvent
    {
        $role = $opts['role'] ?? self::roleFor($actor);

        $event = DB::transaction(function () use ($shipment, $to, $actor, $role, $opts) {
            /** @var Shipment $locked */
            $locked = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if (! $from->canTransitionTo($to, $role)) {
                throw ValidationException::withMessages([
                    'status' => "A {$role} cannot change this shipment from “{$from->label()}” to “{$to->label()}”.",
                ]);
            }

            if ($role === 'rider') {
                $this->assertRiderAssigned($locked, $actor);
            }
            if ($role === 'customer' && $actor && ! Shipment::visibleTo($actor)->whereKey($locked->id)->exists()) {
                throw new AuthorizationException('You do not have access to this shipment.');
            }

            if ($to === ShipmentStatus::Delivered) {
                $proof = $opts['proof'] ?? null;
                if (! $proof instanceof DeliveryProof || $proof->shipment_id !== $locked->id) {
                    throw ValidationException::withMessages(['proof' => 'Proof of delivery is required before marking a shipment delivered.']);
                }
            }

            $now = now();
            $updates = ['status' => $to, 'status_changed_at' => $now];
            if ($to === ShipmentStatus::Booked && ! $locked->booked_at) {
                $updates['booked_at'] = $now;
            }
            if ($to === ShipmentStatus::Delivered) {
                $updates['delivered_at'] = $now;
            }
            if ($to === ShipmentStatus::Cancelled) {
                $updates['cancelled_at'] = $now;
                $updates['cancellation_reason'] = $opts['public_description'] ?? null;
            }
            $locked->forceFill($updates)->save();

            if ($to->isTerminal() || $to === ShipmentStatus::ReturnInitiated) {
                RiderAssignment::where('shipment_id', $locked->id)->whereIn('status', ['assigned', 'accepted'])
                    ->update(['status' => $to === ShipmentStatus::Cancelled ? 'cancelled' : 'completed', 'ended_at' => $now]);
            }

            $event = ShipmentEvent::create([
                'shipment_id' => $locked->id,
                'status' => $to,
                'previous_status' => $from->value,
                'occurred_at' => $opts['occurred_at'] ?? $now,
                'location' => $opts['location'] ?? null,
                'public_description' => trim((string) ($opts['public_description'] ?? '')) ?: $to->publicDescription(),
                'internal_note' => $opts['internal_note'] ?? null,
                'actor_id' => $actor?->id,
                'source' => $opts['source'] ?? $role,
                'delivery_proof_id' => ($opts['proof'] ?? null)?->id,
            ]);

            if (in_array($role, ['admin', 'dispatcher'], true)) {
                Audit::log('shipment.status_changed', $locked, ['from' => $from->value, 'to' => $to->value]);
            }

            $shipment->setRawAttributes($locked->getAttributes(), true);

            return $event;
        });

        if ($eventName = $to->notificationEvent()) {
            $this->notifications->forShipment($shipment, $eventName, $event);
        }

        return $event;
    }

    /** Timeline entry that does not change status (e.g. assignment on a later leg). */
    public function note(Shipment $shipment, string $publicDescription, ?User $actor, string $source, ?string $internal = null): ShipmentEvent
    {
        return ShipmentEvent::create([
            'shipment_id' => $shipment->id,
            'status' => $shipment->status,
            'previous_status' => $shipment->status->value,
            'occurred_at' => now(),
            'public_description' => $publicDescription,
            'internal_note' => $internal,
            'actor_id' => $actor?->id,
            'source' => $source,
        ]);
    }

    public function assertRiderAssigned(Shipment $shipment, ?User $rider): void
    {
        $ok = $rider && RiderAssignment::where('shipment_id', $shipment->id)
            ->where('rider_id', $rider->id)
            ->whereIn('status', ['assigned', 'accepted'])
            ->exists();
        if (! $ok) {
            throw new AuthorizationException('This shipment is not assigned to you.');
        }
    }
}
