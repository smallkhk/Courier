<?php

namespace App\Services;

use App\Models\Business;
use App\Models\PricingRule;
use App\Models\Quote;
use App\Models\Service;
use App\Models\ServiceZone;
use App\Support\Money;
use App\Support\Settings;
use App\Support\Units;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Computes quotes from administrator-configured pricing rules.
 * Totals are always computed here, never taken from client input.
 *
 * Formula (all in integer minor units):
 *   Weights use the rule's unit: lb (dimensions in inches, US divisor ~139) or kg (cm, divisor ~5000).
 *   chargeable      = Σ max(actual weight, L×W×H / volumetric_divisor) per parcel
 *   freight         = base_fee + ceil(max(0, chargeable − included)) × per_unit_fee
 *                     + (parcel_count − 1) × extra_parcel_fee
 *   discount        = freight × discount_percent            (negotiated/business rules)
 *   freight_net     = max(min_charge, freight − discount)
 *   surcharges      = remote_surcharge (if either zone is remote) + pickup_surcharge (if pickup requested)
 *   insurance       = insured ? max(insurance_min_fee, declared_value × insurance_rate_percent) : 0
 *   subtotal        = freight_net + surcharges + insurance
 *   tax             = subtotal × tax_rate_percent
 *   total           = subtotal + tax
 */
class PricingService
{
    /**
     * @param  array{service_id:int, origin_zone_id:int, destination_zone_id:int, parcels: list<array{weight_kg:numeric, length_cm?:numeric|null, width_cm?:numeric|null, height_cm?:numeric|null}>, declared_value?:numeric|null, insured?:bool, pickup_requested?:bool}  $input
     */
    public function quote(array $input, ?int $userId = null, ?Business $business = null): Quote
    {
        $service = Service::where('active', true)->find($input['service_id']);
        $origin = ServiceZone::where('active', true)->find($input['origin_zone_id']);
        $destination = ServiceZone::where('active', true)->find($input['destination_zone_id']);

        if (! $service || ! $origin || ! $destination) {
            throw ValidationException::withMessages(['service_id' => 'The selected service or area is not available.']);
        }
        $this->assertServiceable($service, $origin, $destination, (bool) ($input['pickup_requested'] ?? true));

        $rule = $this->findRule($service, $origin, $destination, $business);
        if (! $rule) {
            throw ValidationException::withMessages(['service_id' => 'No price is configured for this route and service yet. Please contact us for a quote.']);
        }

        $calc = $this->calculate($rule, $input, $origin->is_remote || $destination->is_remote);

        if ($service->max_weight_kg !== null && $calc['chargeable_weight_kg'] > (float) $service->max_weight_kg) {
            throw ValidationException::withMessages(['parcels' => "This service accepts up to {$service->max_weight_kg} kg chargeable weight."]);
        }

        return Quote::create([
            'user_id' => $userId,
            'business_id' => $business?->id,
            'service_id' => $service->id,
            'origin_zone_id' => $origin->id,
            'destination_zone_id' => $destination->id,
            'pricing_rule_id' => $rule->id,
            'inputs' => $input,
            'breakdown' => $calc,
            'total' => $calc['total'],
            'currency' => $rule->currency,
            'expires_at' => now()->addMinutes((int) Settings::get('quote_validity_minutes', 30)),
        ]);
    }

    public function assertServiceable(Service $service, ServiceZone $origin, ServiceZone $destination, bool $pickupRequested): void
    {
        $errors = [];
        if (! $destination->delivery_enabled) {
            $errors['destination_zone_id'] = "We do not currently deliver to {$destination->name}.";
        }
        if ($pickupRequested && ! $origin->pickup_enabled) {
            $errors['origin_zone_id'] = "Pickup is not available in {$origin->name}. You can drop off at a branch instead.";
        }
        $zoneIds = $service->zones()->pluck('service_zones.id')->all();
        if (! in_array($origin->id, $zoneIds) || ! in_array($destination->id, $zoneIds)) {
            $errors['service_id'] = "{$service->name} is not available on this route.";
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** Most specific active rule wins: business > exact route > origin-only/destination-only > any. */
    public function findRule(Service $service, ServiceZone $origin, ServiceZone $destination, ?Business $business = null, ?Carbon $on = null): ?PricingRule
    {
        $date = ($on ?? now())->toDateString();

        $candidates = PricingRule::query()
            ->where('service_id', $service->id)
            ->where('active', true)
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $date))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
            ->where(fn ($q) => $q->whereNull('origin_zone_id')->orWhere('origin_zone_id', $origin->id))
            ->where(fn ($q) => $q->whereNull('destination_zone_id')->orWhere('destination_zone_id', $destination->id))
            ->where(fn ($q) => $business
                ? $q->whereNull('business_id')->orWhere('business_id', $business->id)
                : $q->whereNull('business_id'))
            ->get();

        return $candidates->sortByDesc(fn (PricingRule $r) => ($r->business_id ? 100 : 0)
            + ($r->origin_zone_id ? 10 : 0)
            + ($r->destination_zone_id ? 10 : 0)
            + ($r->effective_from ? 1 : 0))
            ->first();
    }

    /** Pure calculation. Returns amounts as 2-decimal strings plus weight info. */
    public function calculate(PricingRule $rule, array $input, bool $remote): array
    {
        $divisor = max(1, (int) $rule->volumetric_divisor);
        $parcels = $input['parcels'] ?? [];
        if ($parcels === []) {
            throw ValidationException::withMessages(['parcels' => 'Add at least one parcel.']);
        }

        // Work in thousandths of the rule's weight unit (lb or kg) to keep arithmetic exact.
        // Parcels arrive in kg/cm; lb rules convert to lb/inches (US dimensional weight).
        $lb = $rule->weight_unit === 'lb';
        $chargeableMilli = 0;
        foreach ($parcels as $p) {
            $actual = (float) $p['weight_kg'] / ($lb ? Units::LB_TO_KG : 1);
            $vol = 0.0;
            if (! empty($p['length_cm']) && ! empty($p['width_cm']) && ! empty($p['height_cm'])) {
                $f = $lb ? Units::IN_TO_CM : 1;
                $vol = ((float) $p['length_cm'] / $f) * ((float) $p['width_cm'] / $f) * ((float) $p['height_cm'] / $f) / $divisor;
            }
            $chargeableMilli += (int) round(max($actual, $vol) * 1000);
        }
        $parcelCount = count($parcels);

        $includedMilli = (int) round(((float) $rule->included_weight) * 1000);
        $extraMilli = max(0, $chargeableMilli - $includedMilli);
        // Charge per started unit (lb or kg) above the included weight.
        $extraKgBilled = (int) ceil($extraMilli / 1000);
        $unit = $lb ? 'lb' : 'kg';
        $chargeableGrams = (int) round($chargeableMilli * ($lb ? Units::LB_TO_KG : 1));

        $base = Money::toMinor($rule->base_fee);
        $weightFee = $extraKgBilled * Money::toMinor($rule->per_weight_fee);
        $parcelFee = max(0, $parcelCount - 1) * Money::toMinor($rule->extra_parcel_fee);
        $freight = $base + $weightFee + $parcelFee;

        $discount = Money::percentOf($freight, $rule->discount_percent);
        $freightNet = $freight - $discount;
        $minCharge = Money::toMinor($rule->min_charge);
        $minAdjustment = max(0, $minCharge - $freightNet);
        $freightNet += $minAdjustment;

        $remoteFee = $remote ? Money::toMinor($rule->remote_surcharge) : 0;
        $pickupFee = ($input['pickup_requested'] ?? true) ? Money::toMinor($rule->pickup_surcharge) : 0;

        $insurance = 0;
        $declared = Money::toMinor((string) ($input['declared_value'] ?? '0'));
        if (! empty($input['insured'])) {
            $insurance = max(Money::toMinor($rule->insurance_min_fee), Money::percentOf($declared, $rule->insurance_rate_percent));
        }

        $subtotal = $freightNet + $remoteFee + $pickupFee + $insurance;
        $tax = Money::percentOf($subtotal, $rule->tax_rate_percent);
        $total = $subtotal + $tax;

        $lines = array_values(array_filter([
            ['label' => 'Base fee', 'amount' => Money::fromMinor($base)],
            $weightFee ? ['label' => "Additional weight ({$extraKgBilled} {$unit})", 'amount' => Money::fromMinor($weightFee)] : null,
            $parcelFee ? ['label' => 'Additional parcels ('.($parcelCount - 1).')', 'amount' => Money::fromMinor($parcelFee)] : null,
            $discount ? ['label' => 'Discount ('.rtrim(rtrim((string) $rule->discount_percent, '0'), '.').'%)', 'amount' => Money::fromMinor(-$discount)] : null,
            $minAdjustment ? ['label' => 'Minimum charge adjustment', 'amount' => Money::fromMinor($minAdjustment)] : null,
            $remoteFee ? ['label' => 'Remote area surcharge', 'amount' => Money::fromMinor($remoteFee)] : null,
            $pickupFee ? ['label' => 'Pickup fee', 'amount' => Money::fromMinor($pickupFee)] : null,
            $insurance ? ['label' => 'Insurance', 'amount' => Money::fromMinor($insurance)] : null,
            $tax ? ['label' => 'Tax ('.rtrim(rtrim((string) $rule->tax_rate_percent, '0'), '.').'%)', 'amount' => Money::fromMinor($tax)] : null,
        ]));

        return [
            'lines' => $lines,
            'chargeable_weight_kg' => round($chargeableGrams / 1000, 3),
            'chargeable_weight' => round($chargeableMilli / 1000, 3),
            'weight_unit' => $unit,
            'parcel_count' => $parcelCount,
            'subtotal' => Money::fromMinor($subtotal),
            'tax' => Money::fromMinor($tax),
            'total' => Money::fromMinor($total),
            'currency' => $rule->currency,
            'rule_id' => $rule->id,
        ];
    }
}
