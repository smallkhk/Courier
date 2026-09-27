<?php

namespace Tests\Feature;

use App\Models\ServiceZone;
use App\Models\Shipment;
use App\Services\PricingService;
use App\Services\ZoneResolver;
use App\Support\Countries;
use App\Support\Phone;
use App\Support\Regions;
use App\Support\Units;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternationalTest extends TestCase
{
    use RefreshDatabase;

    public function test_zone_resolution_prefers_the_most_specific_match(): void
    {
        ServiceZone::create(['code' => 'NYC', 'name' => 'NYC', 'country_code' => 'US', 'postal_prefixes' => ['100', '112']]);
        ServiceZone::create(['code' => 'NY', 'name' => 'NY state', 'country_code' => 'US', 'regions' => ['NY']]);
        ServiceZone::create(['code' => 'US', 'name' => 'USA', 'country_code' => 'US']);
        ServiceZone::create(['code' => 'CA', 'name' => 'Canada', 'country_code' => 'CA']);
        ServiceZone::create(['code' => 'WORLD', 'name' => 'World', 'country_code' => null]);
        ServiceZone::create(['code' => 'OFF', 'name' => 'Inactive', 'country_code' => 'GB', 'active' => false]);
        $r = new ZoneResolver;

        $this->assertSame('NYC', $r->resolve('US', 'NY', '11201', 'Brooklyn')->code);
        $this->assertSame('NY', $r->resolve('US', 'New York', '12207', 'Albany')->code, 'State names normalise to codes.');
        $this->assertSame('US', $r->resolve('us', 'TX', '73301', 'Austin')->code);
        $this->assertSame('CA', $r->resolve('CA', 'ON', 'M5V 2T6', 'Toronto')->code);
        $this->assertSame('WORLD', $r->resolve('GB', null, 'SW1A 2AA', 'London')->code, 'Inactive zones are ignored.');
        $this->assertSame('WORLD', $r->resolve('JP', null, '100-0001', 'Tokyo')->code);
    }

    public function test_no_zone_means_not_serviceable(): void
    {
        ServiceZone::create(['code' => 'US', 'name' => 'USA', 'country_code' => 'US']);
        $this->assertNull((new ZoneResolver)->resolve('FR', null, '75001', 'Paris'));
    }

    public function test_phone_countries_and_regions(): void
    {
        $this->assertSame('+12125550147', Phone::toE164('(212) 555-0147', 'US'));
        $this->assertSame('+442079460958', Phone::toE164('020 7946 0958', 'GB'));
        $this->assertSame('+442079460958', Phone::toE164('+44 20 7946 0958', 'US'), 'An explicit country code wins.');
        $this->assertNull(Phone::toE164('12345', 'US'));
        $this->assertGreaterThan(240, count(Countries::all()));
        $this->assertSame('United States', Countries::name('US'));
        $this->assertSame('CA', Regions::normalize('US', 'California'));
        $this->assertTrue(Regions::postalValid('US', '10001-1234'));
        $this->assertFalse(Regions::postalValid('US', '1000'));
        $this->assertTrue(Regions::postalValid('CA', 'M5V 2T6'));
        $this->assertTrue(Regions::postalValid('NG', 'anything'), 'Unknown formats are not rejected.');
        $this->assertEqualsWithDelta(0.4536, Units::toKg(1, 'imperial'), 0.0001);
        $this->assertSame('2.2 lb', Units::weight(1, 'imperial'));
    }

    public function test_pound_based_pricing_uses_us_dimensional_weight(): void
    {
        $this->seedNetwork();
        $this->rule->update(['weight_unit' => 'lb', 'volumetric_divisor' => 139, 'included_weight' => 1, 'per_weight_fee' => '1.00', 'base_fee' => '10.00', 'pickup_surcharge' => '0', 'tax_rate_percent' => '0']);
        // 12 x 12 x 12 in box weighing 2 lb: dim weight = 1728 / 139 = 12.43 lb -> billed 12 extra lb above 1 lb included
        $q = app(PricingService::class)->quote([
            'service_id' => $this->service->id, 'origin_zone_id' => $this->origin->id, 'destination_zone_id' => $this->destination->id,
            'parcels' => [['weight_kg' => 2 * Units::LB_TO_KG, 'length_cm' => 12 * 2.54, 'width_cm' => 12 * 2.54, 'height_cm' => 12 * 2.54]],
            'pickup_requested' => false,
        ]);
        $this->assertSame('lb', $q->breakdown['weight_unit']);
        $this->assertEqualsWithDelta(12.432, $q->breakdown['chargeable_weight'], 0.01);
        $this->assertSame('22.00', $q->total); // 10 + ceil(11.43) * 1
    }

    public function test_international_booking_requires_customs_and_stores_it(): void
    {
        $this->seedNetwork();
        $world = ServiceZone::create(['code' => 'WORLD', 'name' => 'World', 'country_code' => null]);
        $this->service->zones()->attach($world->id);
        $user = $this->user();
        $abroad = ['delivery_address' => '10 Downing St', 'delivery_city' => 'London', 'delivery_region' => '', 'delivery_postal_code' => 'SW1A 2AA', 'delivery_country' => 'GB', 'recipient_phone' => '020 7946 0958'];

        $this->actingAs($user)->post(route('book.quote'), $this->bookingPayload($abroad))
            ->assertSessionHasErrors(['customs_contents_type', 'customs_description', 'declared_value']);

        $this->actingAs($user)->post(route('book.quote'), $this->bookingPayload($abroad + ['customs_contents_type' => 'gift', 'customs_description' => '3 paperback books', 'declared_value' => '45', 'customs_hs_code' => '4901.99']))
            ->assertRedirect(route('book.review'));
        $key = session('booking.idempotency');
        $this->post(route('book.confirm'), ['accept_terms' => '1', 'payment_method' => 'online', 'idempotency_key' => $key]);

        $s = Shipment::where('idempotency_key', $key)->firstOrFail();
        $this->assertTrue($s->isInternational());
        $this->assertSame('GB', $s->delivery_country);
        $this->assertSame('+442079460958', $s->recipient_phone);
        $this->assertSame('+12125550147', $s->sender_phone);
        $this->assertSame('gift', $s->customs['contents_type']);
        $this->assertSame('4901.99', $s->customs['hs_code']);
        $this->assertSame($world->id, $s->destination_zone_id);
    }

    public function test_invalid_phone_and_postal_code_are_rejected(): void
    {
        $this->seedNetwork();
        $this->actingAs($this->user())->post(route('book.quote'), $this->bookingPayload(['recipient_phone' => '123', 'delivery_postal_code' => 'ABCDE']))
            ->assertSessionHasErrors(['recipient_phone', 'delivery_postal_code']);
    }

    public function test_saved_address_is_normalised(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post(route('account.addresses.store'), [
            'label' => 'Office', 'contact_name' => 'Me', 'phone' => '416 555 0199',
            'a_address' => '50 Placeholder St', 'a_city' => 'Toronto', 'a_region' => 'Ontario', 'a_postal_code' => 'm5v 2t6', 'a_country' => 'CA',
        ])->assertSessionHasNoErrors();
        $a = $user->addresses()->first();
        $this->assertSame(['ON', 'M5V 2T6', 'CA', '+14165550199'], [$a->region, $a->postal_code, $a->country_code, $a->phone]);
    }

    public function test_quote_api_accepts_locations(): void
    {
        $this->seedNetwork();
        $this->postJson('/api/quotes', [
            'origin' => ['country' => 'US', 'postal_code' => '10001'], 'destination' => ['country' => 'US', 'postal_code' => '11201'],
            'service_id' => $this->service->id, 'units' => 'imperial', 'parcels' => [['weight' => 5]],
        ])->assertCreated()->assertJsonPath('origin_zone', 'T-A')->assertJsonPath('destination_zone', 'T-B');
        $this->postJson('/api/quotes', [
            'origin' => ['country' => 'US', 'postal_code' => '10001'], 'destination' => ['country' => 'FR', 'postal_code' => '75001'],
            'service_id' => $this->service->id, 'parcels' => [['weight' => 5]],
        ])->assertStatus(422)->assertJsonValidationErrors('destination');
    }
}
