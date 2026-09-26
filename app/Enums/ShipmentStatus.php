<?php

namespace App\Enums;

/**
 * Shipment status state machine.
 *
 * TRANSITIONS lists, for each status, the statuses it may move to and which actor
 * roles may perform that move. It is the single source of truth used by the
 * ShipmentWorkflow service, the rider/ops UIs and the tests. See docs/STATUS_WORKFLOW.md.
 *
 * Actor roles: system, customer, rider, dispatcher, admin.
 */
enum ShipmentStatus: string
{
    case Draft = 'draft';
    case PendingPayment = 'pending_payment';
    case Booked = 'booked';
    case PickupScheduled = 'pickup_scheduled';
    case RiderAssigned = 'rider_assigned';
    case PickedUp = 'picked_up';
    case AtOriginFacility = 'at_origin_facility';
    case InTransit = 'in_transit';
    case AtDestinationFacility = 'at_destination_facility';
    case OutForDelivery = 'out_for_delivery';
    case DeliveryAttempted = 'delivery_attempted';
    case DeliveryException = 'delivery_exception';
    case Delivered = 'delivered';
    case ReturnInitiated = 'return_initiated';
    case ReturnInTransit = 'return_in_transit';
    case ReturnedToSender = 'returned_to_sender';
    case Cancelled = 'cancelled';
    case OnHold = 'on_hold';

    private const OPS = ['dispatcher', 'admin'];

    private const FIELD = ['rider', 'dispatcher', 'admin'];

    /** @return array<string, array<string, list<string>>> from => [to => roles] */
    public static function transitions(): array
    {
        $ops = self::OPS;
        $field = self::FIELD;

        return [
            'draft' => ['pending_payment' => ['system', 'customer'], 'booked' => ['system'], 'cancelled' => ['customer', 'admin', 'system']],
            'pending_payment' => ['booked' => ['system', 'admin'], 'cancelled' => ['customer', 'admin', 'system']],
            'booked' => ['pickup_scheduled' => $ops, 'rider_assigned' => ['system', ...$ops], 'at_origin_facility' => $ops, 'on_hold' => $ops, 'cancelled' => ['customer', 'admin']],
            'pickup_scheduled' => ['rider_assigned' => ['system', ...$ops], 'at_origin_facility' => $ops, 'on_hold' => $ops, 'cancelled' => ['customer', 'admin']],
            'rider_assigned' => ['picked_up' => $field, 'out_for_delivery' => $field, 'on_hold' => $ops, 'cancelled' => ['admin']],
            'picked_up' => ['at_origin_facility' => $field, 'in_transit' => $field, 'out_for_delivery' => $field, 'on_hold' => $ops],
            'at_origin_facility' => ['in_transit' => $field, 'out_for_delivery' => $field, 'on_hold' => $ops],
            'in_transit' => ['at_destination_facility' => $field, 'out_for_delivery' => $field, 'on_hold' => $ops],
            'at_destination_facility' => ['out_for_delivery' => $field, 'on_hold' => $ops, 'return_initiated' => $ops],
            'out_for_delivery' => ['delivered' => $field, 'delivery_attempted' => $field, 'delivery_exception' => $field],
            'delivery_attempted' => ['out_for_delivery' => $field, 'at_destination_facility' => $field, 'delivery_exception' => $ops, 'return_initiated' => $ops, 'on_hold' => $ops],
            'delivery_exception' => ['out_for_delivery' => $field, 'at_destination_facility' => $field, 'return_initiated' => $ops, 'on_hold' => $ops],
            'return_initiated' => ['return_in_transit' => $field],
            'return_in_transit' => ['returned_to_sender' => $field],
            'on_hold' => ['booked' => $ops, 'at_origin_facility' => $ops, 'at_destination_facility' => $ops, 'out_for_delivery' => $ops, 'return_initiated' => $ops, 'cancelled' => ['admin']],
            'delivered' => [],
            'returned_to_sender' => [],
            'cancelled' => [],
        ];
    }

    public function canTransitionTo(self $to, string $role): bool
    {
        $roles = self::transitions()[$this->value][$to->value] ?? null;

        return $roles !== null && in_array($role, $roles, true);
    }

    /** @return list<self> */
    public function allowedNext(string $role): array
    {
        $out = [];
        foreach (self::transitions()[$this->value] ?? [] as $to => $roles) {
            if (in_array($role, $roles, true)) {
                $out[] = self::from($to);
            }
        }

        return $out;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::ReturnedToSender, self::Cancelled], true);
    }

    public function isActive(): bool
    {
        return ! $this->isTerminal() && ! in_array($this, [self::Draft, self::PendingPayment], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingPayment => 'Awaiting payment',
            self::Booked => 'Booked',
            self::PickupScheduled => 'Pickup scheduled',
            self::RiderAssigned => 'Rider assigned',
            self::PickedUp => 'Picked up',
            self::AtOriginFacility => 'At origin hub',
            self::InTransit => 'In transit',
            self::AtDestinationFacility => 'At destination hub',
            self::OutForDelivery => 'Out for delivery',
            self::DeliveryAttempted => 'Delivery attempted',
            self::DeliveryException => 'Delivery exception',
            self::Delivered => 'Delivered',
            self::ReturnInitiated => 'Return initiated',
            self::ReturnInTransit => 'Returning to sender',
            self::ReturnedToSender => 'Returned to sender',
            self::Cancelled => 'Cancelled',
            self::OnHold => 'On hold',
        };
    }

    /** Default customer-safe description used when an actor gives none. */
    public function publicDescription(): string
    {
        return match ($this) {
            self::Draft => 'Shipment details saved.',
            self::PendingPayment => 'Shipment created and awaiting payment.',
            self::Booked => 'Shipment confirmed and booked.',
            self::PickupScheduled => 'Pickup has been scheduled.',
            self::RiderAssigned => 'A delivery agent has been assigned.',
            self::PickedUp => 'Parcel collected from the sender.',
            self::AtOriginFacility => 'Parcel received at the origin hub.',
            self::InTransit => 'Parcel is in transit.',
            self::AtDestinationFacility => 'Parcel arrived at the destination hub.',
            self::OutForDelivery => 'Parcel is out for delivery.',
            self::DeliveryAttempted => 'A delivery attempt was made but was not completed.',
            self::DeliveryException => 'There is an issue with this delivery. Our team is reviewing it.',
            self::Delivered => 'Parcel delivered.',
            self::ReturnInitiated => 'A return to the sender has been started.',
            self::ReturnInTransit => 'Parcel is on its way back to the sender.',
            self::ReturnedToSender => 'Parcel returned to the sender.',
            self::Cancelled => 'Shipment cancelled.',
            self::OnHold => 'Shipment is on hold.',
        };
    }

    /** Visual tone for badges. Always rendered together with an icon and text label. */
    public function tone(): string
    {
        return match ($this) {
            self::Delivered, self::ReturnedToSender => 'success',
            self::DeliveryAttempted, self::OnHold, self::PendingPayment => 'warning',
            self::DeliveryException, self::Cancelled => 'danger',
            self::Draft => 'neutral',
            self::OutForDelivery => 'accent',
            default => 'info',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'file',
            self::PendingPayment => 'credit-card',
            self::Booked, self::PickupScheduled => 'calendar',
            self::RiderAssigned => 'user',
            self::PickedUp => 'package',
            self::AtOriginFacility, self::AtDestinationFacility => 'warehouse',
            self::InTransit => 'truck',
            self::OutForDelivery => 'bike',
            self::DeliveryAttempted, self::OnHold => 'clock',
            self::DeliveryException => 'alert',
            self::Delivered => 'check',
            self::ReturnInitiated, self::ReturnInTransit, self::ReturnedToSender => 'undo',
            self::Cancelled => 'x',
        };
    }

    /** Notification event fired when a shipment enters this status (null = none). */
    public function notificationEvent(): ?string
    {
        return match ($this) {
            self::PickupScheduled => 'pickup_scheduled',
            self::RiderAssigned => 'rider_assigned',
            self::PickedUp => 'picked_up',
            self::InTransit => 'in_transit',
            self::OutForDelivery => 'out_for_delivery',
            self::DeliveryAttempted, self::DeliveryException => 'delivery_failed',
            self::Delivered => 'delivered',
            self::ReturnInitiated => 'return_initiated',
            self::ReturnedToSender => 'return_completed',
            self::Cancelled => 'cancelled',
            default => null,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $c) {
            $out[$c->value] = $c->label();
        }

        return $out;
    }
}
