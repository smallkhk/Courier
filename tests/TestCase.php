<?php

namespace Tests;

use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\PricingRule;
use App\Models\RiderProfile;
use App\Models\Service;
use App\Models\ServiceZone;
use App\Models\User;
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
        $this->origin = ServiceZone::create(['code' => 'T-A', 'name' => 'Test A', 'state' => 'Lagos', 'cities' => ['Ikeja', 'Yaba']]);
        $this->destination = ServiceZone::create(['code' => 'T-B', 'name' => 'Test B', 'state' => 'Lagos', 'cities' => ['Lekki']]);
        $this->service = Service::create(['code' => 'EXP', 'name' => 'Express', 'transit_days_min' => 1, 'transit_days_max' => 2]);
        $this->service->zones()->sync([$this->origin->id, $this->destination->id]);
        $this->rule = PricingRule::create(array_merge([
            'name' => 'Test rule', 'service_id' => $this->service->id, 'currency' => 'NGN',
            'base_fee' => '1000.00', 'included_weight_kg' => 1, 'per_kg_fee' => '100.00', 'extra_parcel_fee' => '50.00',
            'min_charge' => '0', 'remote_surcharge' => '0', 'pickup_surcharge' => '200.00',
            'insurance_rate_percent' => '1', 'insurance_min_fee' => '100.00', 'tax_rate_percent' => '7.5',
        ], $rule));
    }

    protected function user(string $role = 'customer', array $attrs = []): User
    {
        $u = User::factory()->create(array_merge(['role' => $role, 'phone' => '08030000000'], $attrs));
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

    /** Valid booking form payload. */
    protected function bookingPayload(array $override = []): array
    {
        return array_merge([
            'sender_name' => 'Ada Sender', 'sender_phone' => '08031112222', 'sender_email' => 'ada@example.com',
            'pickup_address' => '1 Test Road', 'pickup_city' => 'Ikeja', 'pickup_state' => 'Lagos', 'origin_zone_id' => $this->origin->id,
            'recipient_name' => 'Bola Recipient', 'recipient_phone' => '08034445555', 'delivery_address' => '2 Test Close',
            'delivery_city' => 'Lekki', 'delivery_state' => 'Lagos', 'destination_zone_id' => $this->destination->id,
            'package_description' => 'Books', 'package_category' => 'other',
            'parcels' => [['weight_kg' => '2.5']],
            'service_id' => $this->service->id, 'declared_value' => '0', 'insured' => '0',
            'pickup_requested' => '1', 'pickup_date' => now()->addDay()->toDateString(),
        ], $override);
    }
}
