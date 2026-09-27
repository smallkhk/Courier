<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\PricingRule;
use App\Models\RiderProfile;
use App\Models\Service;
use App\Models\ServiceZone;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * DEVELOPMENT / DEMO DATA ONLY. Every record is labelled "DEMO". Prices are
 * placeholders to exercise the pricing engine — they are NOT real rates.
 * Refuses to run in production.
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'password123';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoSeeder must not run in production.');
        }
        $this->call(EssentialSeeder::class);

        Settings::set('business_name', 'Courier (Demo)');
        Settings::set('support_email', 'support@example.com');
        Settings::set('support_phone', '+1 (800) 555-0199');
        Settings::set('office_address', 'DEMO — 1 Example Plaza, New York, NY 10001, USA');

        $nycZips = ['100', '101', '102', '103', '104', '110', '111', '112', '113', '114', '116'];
        $laZips = ['900', '901', '902', '903', '904', '905', '906', '907', '908', '910', '911', '912', '913', '914', '915', '916', '917', '918'];
        $zones = [
            // code, name, country, regions, postal prefixes, remote, lat, lng
            ['US-NYC', 'DEMO New York City', 'US', [], $nycZips, false, 40.7128, -74.0060],
            ['US-NJCT', 'DEMO New Jersey & Connecticut', 'US', ['NJ', 'CT'], [], false, 40.7357, -74.1724],
            ['US-LA', 'DEMO Los Angeles', 'US', [], $laZips, false, 34.0522, -118.2437],
            ['US-REMOTE', 'DEMO Alaska, Hawaii & territories', 'US', ['AK', 'HI', 'PR', 'GU', 'VI', 'AS', 'MP'], [], true, 21.3069, -157.8583],
            ['US-REST', 'DEMO Rest of the United States', 'US', [], [], false, 39.8283, -98.5795],
            ['CA', 'DEMO Canada', 'CA', [], [], false, 43.6532, -79.3832],
            ['INTL', 'DEMO Rest of the world', null, [], [], false, null, null],
        ];
        $zoneModels = [];
        foreach ($zones as [$code, $name, $country, $regions, $prefixes, $remote, $lat, $lng]) {
            $zoneModels[$code] = ServiceZone::updateOrCreate(['code' => $code], [
                'name' => $name, 'country_code' => $country, 'regions' => $regions, 'postal_prefixes' => $prefixes, 'cities' => [],
                'is_remote' => $remote, 'pickup_enabled' => $country !== null, 'center_lat' => $lat, 'center_lng' => $lng,
            ]);
        }
        $us = ['US-NYC', 'US-NJCT', 'US-LA', 'US-REMOTE', 'US-REST'];

        $services = [
            // code, name, description, min days, max days, max kg, sort, zones
            ['SAME-DAY', 'Same Day (Demo)', 'Picked up and delivered the same business day within the New York and Los Angeles metro areas. DEMO service.', 0, 0, 30, 1, ['US-NYC', 'US-NJCT', 'US-LA']],
            ['NEXT-DAY', 'Next Day (Demo)', 'Next business day between covered US cities. DEMO service.', 1, 1, 70, 2, $us],
            ['GROUND', 'Ground (Demo)', 'Economical delivery across the US and Canada. DEMO service.', 2, 5, 70, 3, [...$us, 'CA']],
            ['INTL-EXPRESS', 'International Express (Demo)', 'Door-to-door to 200+ countries and territories with customs paperwork handled. DEMO service.', 3, 7, 70, 4, [...$us, 'CA', 'INTL']],
        ];
        foreach ($services as [$code, $name, $desc, $min, $max, $maxKg, $sort, $zs]) {
            $svc = Service::updateOrCreate(['code' => $code], ['name' => $name, 'description' => $desc, 'transit_days_min' => $min, 'transit_days_max' => $max, 'max_weight_kg' => $maxKg, 'sort_order' => $sort]);
            $svc->zones()->sync(collect($zs)->map(fn ($z) => $zoneModels[$z]->id)->all());
        }

        // DEMO prices in USD per pound — placeholders to exercise the pricing engine, NOT real rates.
        $base = ['currency' => 'USD', 'weight_unit' => 'lb', 'volumetric_divisor' => 139, 'extra_parcel_fee' => '2.00', 'min_charge' => '0',
            'remote_surcharge' => '12.00', 'pickup_surcharge' => '4.00', 'insurance_rate_percent' => '1.000', 'insurance_min_fee' => '3.00', 'tax_rate_percent' => '0'];
        foreach ([
            ['SAME-DAY', '24.00', 5, '1.50'],
            ['NEXT-DAY', '18.00', 2, '1.25'],
            ['GROUND', '9.50', 1, '0.85'],
            ['INTL-EXPRESS', '45.00', 1, '6.00'],
        ] as [$code, $fee, $incl, $perLb]) {
            PricingRule::updateOrCreate(['name' => "DEMO RATE — {$code} (not a real price)"], $base + [
                'service_id' => Service::where('code', $code)->value('id'), 'base_fee' => $fee, 'included_weight' => $incl, 'per_weight_fee' => $perLb,
            ]);
        }
        $hours = ['mon' => '08:00–19:00', 'tue' => '08:00–19:00', 'wed' => '08:00–19:00', 'thu' => '08:00–19:00', 'fri' => '08:00–19:00', 'sat' => '09:00–15:00', 'sun' => null];
        foreach ([
            ['NYC-MAN', 'DEMO Manhattan Hub', 'US-NYC', '450 W 33rd St', 'New York', 'NY', '10001', 'US', 40.7527, -73.9990, '+1 212 555 0101'],
            ['NYC-BK', 'DEMO Brooklyn Pickup Point', 'US-NYC', '1 Example Ave', 'Brooklyn', 'NY', '11201', 'US', 40.6943, -73.9903, '+1 718 555 0102'],
            ['EWR', 'DEMO Newark Sorting Center', 'US-NJCT', '100 Demo Rd', 'Newark', 'NJ', '07114', 'US', 40.7090, -74.1724, '+1 973 555 0103'],
            ['LAX', 'DEMO Los Angeles Hub', 'US-LA', '200 Sample Blvd', 'Los Angeles', 'CA', '90021', 'US', 34.0301, -118.2386, '+1 213 555 0104'],
            ['YYZ', 'DEMO Toronto Depot', 'CA', '50 Placeholder St', 'Toronto', 'ON', 'M5V 2T6', 'CA', 43.6426, -79.3871, '+1 416 555 0105'],
        ] as [$code, $name, $zone, $addr, $city, $region, $postal, $cc, $lat, $lng, $phone]) {
            Branch::updateOrCreate(['code' => $code], [
                'name' => $name, 'service_zone_id' => $zoneModels[$zone]->id, 'address' => $addr, 'city' => $city, 'region' => $region,
                'postal_code' => $postal, 'country_code' => $cc, 'phone' => $phone, 'lat' => $lat, 'lng' => $lng, 'opening_hours' => $hours,
            ]);
        }

        $mk = fn ($email, $name, $role, $phone) => User::updateOrCreate(['email' => $email], [
            'name' => $name, 'role' => $role, 'phone' => $phone, 'password' => self::PASSWORD, 'status' => 'active', 'email_verified_at' => now(),
        ]);
        $mk('admin@example.com', 'Demo Admin', 'admin', '+12125550111');
        $mk('dispatcher@example.com', 'Demo Dispatcher', 'dispatcher', '+12125550112');
        $customer = $mk('customer@example.com', 'Demo Customer', 'customer', '+17185550113');
        foreach ([['rider@example.com', 'Demo Courier One', '+12125550114', 'US-NYC'], ['rider2@example.com', 'Demo Courier Two', '+12135550115', 'US-LA']] as [$email, $name, $phone, $zone]) {
            $r = $mk($email, $name, 'rider', $phone);
            RiderProfile::updateOrCreate(['user_id' => $r->id], ['service_zone_id' => $zoneModels[$zone]->id, 'vehicle_type' => 'van', 'plate_number' => 'DEMO-'.$r->id]);
        }
        $owner = $mk('business@example.com', 'Demo Business Owner', 'customer', '+19735550116');
        $biz = Business::updateOrCreate(['name' => 'DEMO Retail Inc.'], ['email' => 'business@example.com', 'phone' => '+19735550116', 'status' => 'approved', 'payment_terms' => 'prepaid', 'approved_at' => now()]);
        BusinessMember::updateOrCreate(['business_id' => $biz->id, 'user_id' => $owner->id], ['role' => 'owner']);
        Address::updateOrCreate(['business_id' => $biz->id, 'label' => 'DEMO Warehouse'], [
            'contact_name' => 'Warehouse desk', 'phone' => '+19735550116', 'line1' => '300 Demo Industrial Rd', 'city' => 'Newark', 'region' => 'NJ',
            'postal_code' => '07105', 'country_code' => 'US', 'lat' => 40.7245, 'lng' => -74.1480,
        ]);
        Address::updateOrCreate(['user_id' => $customer->id, 'label' => 'Home'], [
            'contact_name' => 'Demo Customer', 'phone' => '+17185550113', 'line1' => '1 Example Pl', 'line2' => 'Apt 4', 'city' => 'Brooklyn', 'region' => 'NY',
            'postal_code' => '11201', 'country_code' => 'US', 'lat' => 40.6955, 'lng' => -73.9935,
        ]);

        $this->command?->warn('DEMO data seeded. Logins (password: '.self::PASSWORD.'): admin@, dispatcher@, customer@, business@, rider@, rider2@ example.com');
    }
}
