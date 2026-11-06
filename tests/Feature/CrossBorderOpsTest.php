<?php

namespace Tests\Feature;

use App\Models\PricingRule;
use App\Models\Service;
use App\Models\ServiceZone;
use App\Models\Shipment;
use Database\Seeders\EssentialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Texas → Beijing booking seen by operations, with couriers on file (regression for the rider-suggestion crash). */
class CrossBorderOpsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_and_work_a_us_to_china_shipment(): void
    {
        $this->seed(EssentialSeeder::class);
        $us = ServiceZone::create(['code' => 'US', 'name' => 'United States', 'country_code' => 'US']);
        $cn = ServiceZone::create(['code' => 'CN', 'name' => 'China', 'country_code' => 'CN']);
        ServiceZone::create(['code' => 'US-TX', 'name' => 'Texas', 'country_code' => 'US', 'regions' => ['TX']]);
        ServiceZone::create(['code' => 'CN-BJ', 'name' => 'Beijing', 'country_code' => 'CN', 'regions' => ['Beijing', 'Beijing Shi']]);
        $svc = Service::create(['code' => 'INTL-US-CN', 'name' => 'International Express', 'transit_days_min' => 4, 'transit_days_max' => 7]);
        $svc->zones()->sync([$us->id, $cn->id]);
        PricingRule::create(['name' => 'US-CN', 'service_id' => $svc->id, 'origin_zone_id' => $us->id, 'destination_zone_id' => $cn->id, 'currency' => 'USD', 'weight_unit' => 'kg', 'volumetric_divisor' => 5000, 'base_fee' => 100, 'included_weight' => 1, 'per_weight_fee' => 45]);
        $r1 = $this->user('rider', ['email' => 'r1@x.test']);
        $r1->riderProfile->update(['service_zone_id' => $us->id]);
        $r2 = $this->user('rider', ['email' => 'r2@x.test']);
        $admin = $this->user('admin');
        $cust = $this->user('customer');
        $this->service = $svc;
        $payload = $this->bookingPayload([
            'pickup_city' => 'Pearland', 'pickup_region' => 'TX', 'pickup_postal_code' => '77584-1763', 'pickup_country' => 'US',
            'delivery_address' => '71 Di An Men Xi Da Jie', 'delivery_city' => 'Xi Cheng Qu', 'delivery_region' => 'Bei Jing Shi', 'delivery_postal_code' => '100035', 'delivery_country' => 'CN',
            'recipient_phone' => '138 0013 8000', 'parcels' => [['weight' => '10']], 'declared_value' => '350', 'insured' => '1',
            'special_handling' => ['fragile', 'keep_upright'], 'pickup_requested' => '0',
            'customs_contents_type' => 'personal', 'customs_description' => 'Two cotton shirts', 'service_id' => $svc->id,
        ]);
        $this->actingAs($cust)->post('/send/quote', $payload)->assertSessionHasNoErrors();
        $this->actingAs($cust)->post('/send/confirm', ['accept_terms' => '1', 'payment_method' => 'online', 'idempotency_key' => (string) Str::uuid()])->assertRedirect();
        $s = Shipment::latest('id')->firstOrFail();
        $this->actingAs($admin)->get('/ops/shipments/'.$s->tracking_number)->assertOk();
        $this->actingAs($admin)->post('/ops/shipments/'.$s->tracking_number.'/mark-paid', ['reference' => 'CASH1', 'note' => 'x']);
        $this->assertSame('booked', $s->refresh()->status->value);
        $this->actingAs($admin)->get('/ops/shipments/'.$s->tracking_number)->assertOk()
            ->assertSee('Assign to')->assertSee($r1->name)->assertSee('in zone');
        $this->assertSame(['US-TX', 'CN-BJ'], [$s->originZone->code, $s->destinationZone->code]);
        $this->assertSame('505.00', (string) $s->total, 'kg pricing: $100 + 9 × $45');
    }
}
