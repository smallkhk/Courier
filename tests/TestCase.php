<?php

namespace Tests;

use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\PricingRule;
use App\Models\RiderProfile;
use App\Models\Service;
use App\Models\ServiceZone;
use App\Models\User;
use App\Services\ShipmentDetails;
use Database\Seeders\EssentialSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected ServiceZone $origin;

    protected ServiceZone $destination;

    protected Service $service;

    protected PricingRule $rule;

    /** A minimal coverage network with a clearly fake test rate. */
    protected function seedNetwork(array $rule = []): void
    {
        Cache::flush();
        $this->seed(EssentialSeeder::class);
        $this->origin = ServiceZone::create(['code' => 'T-A', 'name' => 'Test Manhattan', 'country_code' => 'US', 'postal_prefixes' => ['100']]);
        $this->destination = ServiceZone::create(['code' => 'T-B', 'name' => 'Test Brooklyn', 'country_code' => 'US', 'postal_prefixes' => ['112']]);
        $this->service = Service::create(['code' => 'EXP', 'name' => 'Express', 'transit_days_min' => 1, 'transit_days_max' => 2]);
        $this->service->zones()->sync([$this->origin->id, $this->destination->id]);
        $this->rule = PricingRule::create(array_merge([
            'name' => 'Test rule', 'service_id' => $this->service->id, 'currency' => 'USD', 'weight_unit' => 'kg', 'volumetric_divisor' => 5000,
            'base_fee' => '1000.00', 'included_weight' => 1, 'per_weight_fee' => '100.00', 'extra_parcel_fee' => '50.00',
            'min_charge' => '0', 'remote_surcharge' => '0', 'pickup_surcharge' => '200.00',
            'insurance_rate_percent' => '1', 'insurance_min_fee' => '100.00', 'tax_rate_percent' => '7.5',
        ], $rule));
    }

    protected function user(string $role = 'customer', array $attrs = []): User
    {
        $u = User::factory()->create(array_merge(['role' => $role, 'phone' => '+12125550100'], $attrs));
        if ($role === 'rider') {
            RiderProfile::create(['user_id' => $u->id, 'on_duty' => true]);
        }

        return $u->fresh();
    }

    protected function businessWith(string $role = 'owner', array $biz = []): array
    {
        $b = Business::create(array_merge(['name' => 'Biz '.uniqid(), 'email' => 'b@example.com', 'status' => 'approved'], $biz));
        $u = $this->user();
        BusinessMember::create(['business_id' => $b->id, 'user_id' => $u->id, 'role' => $role]);

        return [$b, $u];
    }

    /** Booking details after server-side normalisation (zones resolved, kg/cm, E.164 phones). */
    protected function normalizedPayload(array $override = []): array
    {
        return app(ShipmentDetails::class)->normalize($this->bookingPayload($override));
    }

    /** Valid booking form payload. */
    protected function bookingPayload(array $override = []): array
    {
        return array_merge([
            'sender_name' => 'Ada Sender', 'sender_phone' => '(212) 555-0147', 'sender_email' => 'ada@example.com',
            'pickup_address' => '1 Test Road', 'pickup_city' => 'New York', 'pickup_region' => 'NY', 'pickup_postal_code' => '10001', 'pickup_country' => 'US',
            'recipient_name' => 'Bola Recipient', 'recipient_phone' => '(718) 555-0148', 'delivery_address' => '2 Test Close',
            'delivery_city' => 'Brooklyn', 'delivery_region' => 'NY', 'delivery_postal_code' => '11201', 'delivery_country' => 'US',
            'package_description' => 'Books', 'package_category' => 'other',
            'units' => 'metric',
            'parcels' => [['weight' => '2.5']],
            'service_id' => $this->service->id, 'declared_value' => '0', 'insured' => '0',
            'pickup_requested' => '1', 'pickup_date' => now()->addDay()->toDateString(),
        ], $override);
    }
}
