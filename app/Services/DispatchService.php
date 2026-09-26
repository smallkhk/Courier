<?php

namespace App\Services;

use App\Enums\ShipmentStatus;
use App\Models\RiderAssignment;
use App\Models\RiderProfile;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Support\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DispatchService
{
    public function __construct(private ShipmentWorkflow $workflow, private NotificationService $notifications) {}

    public function assign(Shipment $shipment, User $rider, User $by, string $leg = 'delivery', ?string $note = null): RiderAssignment
    {
        if (! $by->isStaff()) {
            throw new AuthorizationException('Only operations staff can assign riders.');
        }
        $this->assertEligible($shipment, $rider);

        $assignment = DB::transaction(function () use ($shipment, $rider, $by, $leg, $note) {
            Shipment::whereKey($shipment->id)->lockForUpdate()->first();
            $previous = RiderAssignment::where('shipment_id', $shipment->id)->whereIn('status', ['assigned', 'accepted'])->get();
            foreach ($previous as $p) {
                $p->forceFill(['status' => 'reassigned', 'ended_at' => now()])->save();
            }

            $a = RiderAssignment::create([
                'shipment_id' => $shipment->id,
                'rider_id' => $rider->id,
                'assigned_by' => $by->id,
                'leg' => $leg,
                'status' => 'assigned',
                'assigned_at' => now(),
                'note' => $note,
            ]);
            Audit::log($previous->isEmpty() ? 'dispatch.assigned' : 'dispatch.reassigned', $shipment, [
                'rider_id' => $rider->id, 'leg' => $leg, 'previous_riders' => $previous->pluck('rider_id')->all(),
            ]);

            return $a;
        });

        $shipment->refresh();
        if (in_array($shipment->status, [ShipmentStatus::Booked, ShipmentStatus::PickupScheduled], true)) {
            $this->workflow->transition($shipment, ShipmentStatus::RiderAssigned, $by, [
                'internal_note' => "Assigned to {$rider->name} ({$leg})",
            ]);
        } else {
            $this->workflow->note($shipment, 'A delivery agent has been assigned to the next leg of this shipment.', $by, $by->role, "Assigned to {$rider->name} ({$leg})");
        }
        $this->notifications->toRider($rider, $shipment, $assignment->id);

        return $assignment;
    }

    public function assertEligible(Shipment $shipment, User $rider): void
    {
        $profile = $rider->riderProfile;
        $errors = [];
        if (! $rider->isRole('rider') || ! $rider->isActive() || ! $profile || ! $profile->is_active) {
            $errors[] = 'This rider is not active.';
        } elseif ($profile->activeAssignmentCount() >= $profile->max_active_assignments) {
            $errors[] = "This rider already has the maximum of {$profile->max_active_assignments} active assignments.";
        }
        if ($shipment->status->isTerminal() || in_array($shipment->status, [ShipmentStatus::Draft, ShipmentStatus::PendingPayment], true)) {
            $errors[] = 'This shipment cannot be assigned in its current status ('.$shipment->status->label().').';
        }
        if ($errors) {
            throw ValidationException::withMessages(['rider_id' => implode(' ', $errors)]);
        }
    }

    public function respond(RiderAssignment $assignment, User $rider, bool $accept, ?string $reason = null): void
    {
        if ($assignment->rider_id !== $rider->id) {
            throw new AuthorizationException('This assignment is not yours.');
        }
        if ($assignment->status !== 'assigned') {
            throw ValidationException::withMessages(['assignment' => 'This assignment has already been answered.']);
        }
        $assignment->forceFill([
            'status' => $accept ? 'accepted' : 'declined',
            'responded_at' => now(),
            'ended_at' => $accept ? null : now(),
            'note' => $accept ? $assignment->note : trim(($assignment->note ? $assignment->note.' | ' : '').'Declined: '.$reason),
        ])->save();
        Audit::log($accept ? 'dispatch.accepted' : 'dispatch.declined', $assignment->shipment, ['rider_id' => $rider->id, 'reason' => $reason]);
    }

    /**
     * Rule-based suggestion (NOT optimisation): active, on-duty riders in the destination
     * zone (or origin zone for pickups) with spare capacity, fewest open jobs first.
     *
     * @return Collection<int, RiderProfile>
     */
    public function suggestRiders(Shipment $shipment, string $leg = 'delivery'): Collection
    {
        $zoneId = $leg === 'pickup' ? $shipment->origin_zone_id : $shipment->destination_zone_id;

        return RiderProfile::with('user')
            ->where('is_active', true)
            ->whereHas('user', fn ($q) => $q->where('status', 'active')->where('role', 'rider'))
            ->get()
            ->map(function (RiderProfile $p) use ($zoneId) {
                $p->open_jobs = $p->activeAssignmentCount();
                $p->in_zone = $p->service_zone_id === $zoneId;

                return $p;
            })
            ->filter(fn ($p) => $p->open_jobs < $p->max_active_assignments)
            ->sortBy(fn ($p) => [! $p->in_zone, ! $p->on_duty, $p->open_jobs])
            ->values();
    }

    /** Shipments that need a rider: booked / at hub with no open assignment. */
    public function unassignedQuery()
    {
        return Shipment::query()
            ->whereIn('status', [ShipmentStatus::Booked, ShipmentStatus::PickupScheduled, ShipmentStatus::AtOriginFacility, ShipmentStatus::AtDestinationFacility, ShipmentStatus::DeliveryAttempted, ShipmentStatus::ReturnInitiated])
            ->whereDoesntHave('assignments', fn ($q) => $q->whereIn('status', ['assigned', 'accepted']));
    }
}
