<?php

namespace App\Services;

use App\Enums\ShipmentStatus;
use App\Models\ServiceZone;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\User;
use App\Support\Audit;
use App\Support\Countries;
use App\Support\Phone;
use App\Support\Regions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staff corrections to a shipment's contact and address details (wrong phone, typo,
 * sender and recipient entered the wrong way round). The price already quoted or paid
 * is never changed; coverage zones are re-resolved and the service must still cover them.
 * Every change is written to the timeline (internal note) and the audit log.
 */
class ShipmentEditor
{
    public const CONTACT = ['name', 'phone', 'email'];

    public const ADDRESS = ['address', 'address2', 'city', 'region', 'postal_code', 'country'];

    public function __construct(private ZoneResolver $zones, private ShipmentWorkflow $workflow) {}

    public static function editable(Shipment $s): bool
    {
        return ! $s->status->isTerminal();
    }

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        $rules = [
            'package_description' => 'required|string|max:200',
            'pickup_instructions' => 'nullable|string|max:500',
            'delivery_instructions' => 'nullable|string|max:500',
            'reason' => 'required|string|max:300',
        ];
        foreach (['sender', 'recipient'] as $who) {
            $rules["{$who}_name"] = 'required|string|max:120';
            $rules["{$who}_phone"] = 'required|string|max:32';
            $rules["{$who}_email"] = ($who === 'sender' ? 'required' : 'nullable').'|email:rfc|max:190';
        }

        return $rules + ShipmentDetails::addressRules('pickup', false) + ShipmentDetails::addressRules('delivery', false);
    }

    /** Apply corrected values; returns the list of changed fields. */
    public function update(Shipment $s, array $d, User $by): array
    {
        abort_unless(self::editable($s), 422, 'Delivered, returned or cancelled shipments can no longer be edited.');
        $errors = [];
        foreach (['pickup' => 'sender', 'delivery' => 'recipient'] as $side => $who) {
            $c = strtoupper($d["{$side}_country"]);
            $d["{$side}_country"] = $c;
            $d["{$side}_region"] = Regions::normalize($c, $d["{$side}_region"] ?? null);
            $postal = strtoupper(trim((string) ($d["{$side}_postal_code"] ?? '')));
            $d["{$side}_postal_code"] = $postal !== '' ? $postal : null;
            if (Regions::postalRequired($c) && ! $postal) {
                $errors["{$side}_postal_code"] = 'A ZIP / postal code is required for '.Countries::name($c).'.';
            } elseif ($postal && ! Regions::postalValid($c, $postal)) {
                $errors["{$side}_postal_code"] = 'That ZIP / postal code doesn\'t look right for '.Countries::name($c).'.';
            }
            if (Regions::for($c) && ! $d["{$side}_region"]) {
                $errors["{$side}_region"] = 'Choose a state / province.';
            }
            $e164 = Phone::toE164($d["{$who}_phone"], $c);
            if ($e164) {
                $d["{$who}_phone"] = $e164;
            } else {
                $errors["{$who}_phone"] = 'Enter a valid phone number (with country code if outside '.Countries::name($c).').';
            }
        }
        if (! $s->customs && $d['pickup_country'] !== $d['delivery_country']) {
            $errors['delivery_country'] = 'This was booked as a domestic shipment, so it has no customs declaration. Cancel it and book an international shipment instead.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $origin = $this->zones->resolve($d['pickup_country'], $d['pickup_region'], $d['pickup_postal_code'], $d['pickup_city']);
        $dest = $this->zones->resolve($d['delivery_country'], $d['delivery_region'], $d['delivery_postal_code'], $d['delivery_city']);
        $covered = $s->service->zones()->pluck('service_zones.id');
        $covers = fn ($z) => $z && $z->lineage()->pluck('id')->intersect($covered)->isNotEmpty();
        if (! $covers($origin)) {
            $errors['pickup_address'] = "{$s->service->name} doesn't pick up from that address.";
        }
        if (! $covers($dest)) {
            $errors['delivery_address'] = "{$s->service->name} doesn't deliver to that address.";
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $new = ['origin_zone_id' => $origin->id, 'destination_zone_id' => $dest->id];
        foreach (array_keys(self::rules()) as $f) {
            if ($f !== 'reason' && ! preg_match('/_(lat|lng|place_id)$/', $f)) {
                $new[$f] = ($d[$f] ?? null) === '' ? null : ($d[$f] ?? null);
            }
        }
        foreach (['pickup', 'delivery'] as $side) {
            // The map pin no longer describes a changed address.
            if ($s->addressLine($side) !== $this->line($new, $side)) {
                $new += ["{$side}_lat" => null, "{$side}_lng" => null, "{$side}_place_id" => null];
            }
        }
        if ($s->customs) {
            $new['customs'] = array_merge($s->customs, ['origin_country' => $new['pickup_country'], 'destination_country' => $new['delivery_country']]);
        }

        return $this->apply($s, $new, $by, $d['reason']);
    }

    /** Sender and recipient were entered the wrong way round. */
    public function swap(Shipment $s, User $by, string $reason): array
    {
        abort_unless(self::editable($s), 422, 'Delivered, returned or cancelled shipments can no longer be edited.');
        $new = [
            'origin_zone_id' => $s->destination_zone_id, 'destination_zone_id' => $s->origin_zone_id,
            'pickup_instructions' => $s->delivery_instructions, 'delivery_instructions' => $s->pickup_instructions,
        ];
        foreach (self::CONTACT as $f) {
            $new["sender_{$f}"] = $s->{"recipient_{$f}"};
            $new["recipient_{$f}"] = $s->{"sender_{$f}"};
        }
        foreach ([...self::ADDRESS, 'lat', 'lng', 'place_id'] as $f) {
            $new["pickup_{$f}"] = $s->{"delivery_{$f}"};
            $new["delivery_{$f}"] = $s->{"pickup_{$f}"};
        }
        $new['sender_email'] ??= $s->sender_email; // sender email is required; keep it if the recipient had none
        $covered = $s->service->zones()->pluck('service_zones.id');
        foreach (['origin_zone_id' => 'pickup_address', 'destination_zone_id' => 'delivery_address'] as $col => $field) {
            if (ServiceZone::find($new[$col])?->lineage()->pluck('id')->intersect($covered)->isEmpty()) {
                throw ValidationException::withMessages([$field => "{$s->service->name} doesn't cover the swapped route. Cancel and rebook it instead."]);
            }
        }
        if ($s->customs) {
            $new['customs'] = array_merge($s->customs, ['origin_country' => $new['pickup_country'], 'destination_country' => $new['delivery_country']]);
        }

        return $this->apply($s, $new, $by, $reason, 'Sender and recipient swapped');
    }

    private function apply(Shipment $s, array $new, User $by, string $reason, ?string $summary = null): array
    {
        $before = $s->only(array_keys($new));
        $changed = array_keys(array_filter($new, fn ($v, $k) => $this->differs($before[$k] ?? null, $v), ARRAY_FILTER_USE_BOTH));
        if (! $changed) {
            return [];
        }
        DB::transaction(function () use ($s, $new, $by, $reason, $summary, $changed, $before) {
            $s->forceFill($new)->save();
            $this->workflow->note($s, 'Shipment details updated by our team.', $by, $by->role,
                ($summary ?? 'Details corrected').': '.implode(', ', $changed).'. Reason: '.$reason);
            Audit::log('shipment.details_edited', $s, [
                'reason' => $reason, 'changed' => $changed,
                'before' => array_intersect_key($before, array_flip($changed)), 'after' => array_intersect_key($new, array_flip($changed)),
            ]);
        });

        return $changed;
    }

    private function differs(mixed $a, mixed $b): bool
    {
        return is_array($a) || is_array($b) ? json_encode($a) !== json_encode($b) : (string) $a !== (string) $b;
    }

    private function line(array $d, string $side): string
    {
        return (new Shipment)->forceFill(array_intersect_key($d, array_flip(array_map(fn ($f) => "{$side}_{$f}", self::ADDRESS))))->addressLine($side);
    }

    /**
     * Admin only: put a paid shipment back to "Booked" as if no work had started. Open courier
     * jobs are ended and the customer's tracking restarts from here; the earlier history stays
     * in the staff timeline and the audit log (events are never deleted).
     */
    public function resetToBooked(Shipment $s, User $by, string $reason): void
    {
        abort_unless($by->isRole('admin'), 403);
        abort_if($s->status->isTerminal() || in_array($s->status->value, ['draft', 'pending_payment', 'booked'], true), 422,
            'Only shipments that are in progress can be reset to Booked.');
        DB::transaction(function () use ($s, $by, $reason) {
            $from = $s->status->value;
            $s->assignments()->whereIn('status', ['assigned', 'accepted'])->update(['status' => 'reassigned', 'ended_at' => now()]);
            $s->forceFill(['status' => ShipmentStatus::Booked, 'status_changed_at' => now(), 'delivered_at' => null])->save();
            ShipmentEvent::create([
                'shipment_id' => $s->id, 'status' => 'booked', 'previous_status' => $from, 'occurred_at' => now(),
                'public_description' => 'Shipment booked.', 'internal_note' => 'Reset to Booked by '.$by->name.'. Reason: '.$reason,
                'actor_id' => $by->id, 'source' => 'reset',
            ]);
            Audit::log('shipment.reset_to_booked', $s, ['from' => $from, 'reason' => $reason]);
        });
    }
}
