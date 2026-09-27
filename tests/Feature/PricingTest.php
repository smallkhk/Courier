<?php

namespace Tests\Feature;

use App\Models\PricingRule;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PricingTest extends TestCase
{
    use RefreshDatabase;

    private function input(array $o = []): array
    {
        return array_merge(['service_id' => $this->service->id, 'origin_zone_id' => $this->origin->id, 'destination_zone_id' => $this->destination->id,
            'parcels' => [['weight_kg' => 2.5]], 'declared_value' => 0, 'insured' => false, 'pickup_requested' => true], $o);
    }

    public function test_quote_breakdown(): void
    {
        $this->seedNetwork();
        $q = app(PricingService::class)->quote($this->input());
        // base 1000 + ceil(1.5)=2kg*100 + pickup 200 = 1400; tax 7.5% = 105; total 1505
        $this->assertSame('1505.00', $q->total);
        $this->assertSame('105.00', $q->breakdown['tax']);
        $this->assertEquals(2.5, $q->breakdown['chargeable_weight_kg']);
    }

    public function test_volumetric_weight_parcels_insurance_and_minimum(): void
    {
        $this->seedNetwork(['min_charge' => '5000.00']);
        $q = app(PricingService::class)->quote($this->input([
            'parcels' => [['weight_kg' => 1, 'length_cm' => 50, 'width_cm' => 40, 'height_cm' => 30], ['weight_kg' => 1]],
            'insured' => true, 'declared_value' => '50000', 'pickup_requested' => false,
        ]));
        // volumetric 60000/5000 = 12kg + 1kg = 13kg chargeable
        $this->assertEquals(13, $q->breakdown['chargeable_weight_kg']);
        // freight 1000 + 12*100 + 50 = 2250 -> min charge 5000; insurance max(100, 500)=500; subtotal 5500; tax 412.50
        $this->assertSame('5912.50', $q->total);
    }

    public function test_business_rule_overrides_public_rule(): void
    {
        $this->seedNetwork();
        [$biz] = $this->businessWith();
        PricingRule::create($this->rule->only(['service_id', 'currency', 'included_weight', 'per_weight_fee', 'extra_parcel_fee', 'min_charge', 'remote_surcharge', 'pickup_surcharge', 'insurance_rate_percent', 'insurance_min_fee', 'tax_rate_percent'])
            + ['name' => 'Negotiated', 'business_id' => $biz->id, 'base_fee' => '500.00']);
        $pub = app(PricingService::class)->quote($this->input());
        $neg = app(PricingService::class)->quote($this->input(), null, $biz);
        $this->assertTrue((float) $neg->total < (float) $pub->total);
    }

    public function test_unserviceable_route_is_rejected(): void
    {
        $this->seedNetwork();
        $this->destination->update(['delivery_enabled' => false]);
        $this->expectException(ValidationException::class);
        app(PricingService::class)->quote($this->input());
    }

    public function test_no_rule_means_no_price(): void
    {
        $this->seedNetwork();
        $this->rule->update(['active' => false]);
        $this->expectException(ValidationException::class);
        app(PricingService::class)->quote($this->input());
    }
}
