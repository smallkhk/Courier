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
        Settings::set('support_phone', '+234 800 000 0000');
        Settings::set('office_address', 'DEMO — 1 Example Road, Ikeja, Lagos');

        $zones = [
            ['LAG-MAIN', 'DEMO Lagos Mainland', 'Lagos', ['Ikeja', 'Yaba', 'Surulere', 'Maryland', 'Ogba', 'Mushin'], false, 6.5965, 3.3421],
            ['LAG-ISL', 'DEMO Lagos Island', 'Lagos', ['Victoria Island', 'Ikoyi', 'Lekki', 'Lagos Island', 'Ajah'], false, 6.4431, 3.4550],
            ['ABJ', 'DEMO Abuja FCT', 'FCT', ['Garki', 'Wuse', 'Maitama', 'Asokoro', 'Gwarinpa'], false, 9.0579, 7.4951],
            ['PHC', 'DEMO Port Harcourt', 'Rivers', ['GRA', 'Trans Amadi', 'Rumuokoro', 'Diobu'], false, 4.8156, 7.0498],
            ['LAG-EPE', 'DEMO Epe (remote)', 'Lagos', ['Epe'], true, 6.5841, 3.9834],
        ];
        $zoneModels = [];
        foreach ($zones as [$code, $name, $state, $cities, $remote, $lat, $lng]) {
            $zoneModels[$code] = ServiceZone::updateOrCreate(['code' => $code], [
                'name' => $name, 'state' => $state, 'cities' => $cities, 'is_remote' => $remote,
                'pickup_enabled' => ! $remote, 'center_lat' => $lat, 'center_lng' => $lng,
            ]);
        }

        $services = [
            ['SAME-DAY', 'Same Day (Demo)', 'Collected and delivered on the same business day within a city. DEMO service.', 0, 0, 10, 1],
            ['EXPRESS', 'Express (Demo)', 'Next business day between covered cities. DEMO service.', 1, 1, 30, 2],
            ['STANDARD', 'Standard (Demo)', 'Economical delivery between covered cities. DEMO service.', 2, 4, 70, 3],
        ];
        foreach ($services as [$code, $name, $desc, $min, $max, $maxKg, $sort]) {
            $svc = Service::updateOrCreate(['code' => $code], ['name' => $name, 'description' => $desc, 'transit_days_min' => $min, 'transit_days_max' => $max, 'max_weight_kg' => $maxKg, 'sort_order' => $sort]);
            $zoneIds = $code === 'SAME-DAY' ? [$zoneModels['LAG-MAIN']->id, $zoneModels['LAG-ISL']->id] : collect($zoneModels)->pluck('id')->all();
            $svc->zones()->sync($zoneIds);
        }

        $base = ['currency' => 'NGN', 'included_weight_kg' => 2, 'per_kg_fee' => '300.00', 'extra_parcel_fee' => '500.00', 'min_charge' => '1500.00',
            'remote_surcharge' => '1500.00', 'pickup_surcharge' => '500.00', 'insurance_rate_percent' => '1.000', 'insurance_min_fee' => '200.00', 'tax_rate_percent' => '7.500'];
        foreach ([['SAME-DAY', '3500.00'], ['EXPRESS', '6000.00'], ['STANDARD', '4000.00']] as [$code, $fee]) {
            PricingRule::updateOrCreate(['name' => "DEMO RATE — {$code} (not a real price)"], $base + ['service_id' => Service::where('code', $code)->value('id'), 'base_fee' => $fee]);
        }

        foreach ([
            ['IKJ', 'DEMO Ikeja Hub', 'LAG-MAIN', '10 Demo Avenue', 'Ikeja', 'Lagos', 6.6018, 3.3515],
            ['VI', 'DEMO Victoria Island Pickup Point', 'LAG-ISL', '5 Sample Street', 'Victoria Island', 'Lagos', 6.4281, 3.4219],
            ['ABJ', 'DEMO Abuja Hub', 'ABJ', '22 Placeholder Close', 'Wuse', 'FCT', 9.0765, 7.4786],
        ] as [$code, $name, $zone, $addr, $city, $state, $lat, $lng]) {
            Branch::updateOrCreate(['code' => $code], [
                'name' => $name, 'service_zone_id' => $zoneModels[$zone]->id, 'address' => $addr, 'city' => $city, 'state' => $state,
                'phone' => '+234 800 000 0000', 'lat' => $lat, 'lng' => $lng,
                'opening_hours' => ['mon' => '08:00–18:00', 'tue' => '08:00–18:00', 'wed' => '08:00–18:00', 'thu' => '08:00–18:00', 'fri' => '08:00–18:00', 'sat' => '09:00–14:00', 'sun' => null],
            ]);
        }

        $mk = fn ($email, $name, $role, $phone) => User::updateOrCreate(['email' => $email], [
            'name' => $name, 'role' => $role, 'phone' => $phone, 'password' => self::PASSWORD, 'status' => 'active', 'email_verified_at' => now(),
        ]);
        $mk('admin@example.com', 'Demo Admin', 'admin', '08000000001');
        $mk('dispatcher@example.com', 'Demo Dispatcher', 'dispatcher', '08000000002');
        $customer = $mk('customer@example.com', 'Demo Customer', 'customer', '08000000003');
        foreach ([['rider@example.com', 'Demo Rider One', '08000000004', 'LAG-MAIN'], ['rider2@example.com', 'Demo Rider Two', '08000000005', 'LAG-ISL']] as [$email, $name, $phone, $zone]) {
            $r = $mk($email, $name, 'rider', $phone);
            RiderProfile::updateOrCreate(['user_id' => $r->id], ['service_zone_id' => $zoneModels[$zone]->id, 'vehicle_type' => 'motorcycle', 'plate_number' => 'DEMO-'.$r->id]);
        }
        $owner = $mk('business@example.com', 'Demo Business Owner', 'customer', '08000000006');
        $biz = Business::updateOrCreate(['name' => 'DEMO Retail Ltd'], ['email' => 'business@example.com', 'phone' => '08000000006', 'status' => 'approved', 'payment_terms' => 'prepaid', 'approved_at' => now()]);
        BusinessMember::updateOrCreate(['business_id' => $biz->id, 'user_id' => $owner->id], ['role' => 'owner']);
        Address::updateOrCreate(['business_id' => $biz->id, 'label' => 'DEMO Warehouse'], [
            'contact_name' => 'Warehouse desk', 'phone' => '08000000006', 'line1' => '3 Demo Industrial Road', 'city' => 'Ikeja', 'state' => 'Lagos', 'service_zone_id' => $zoneModels['LAG-MAIN']->id,
        ]);
        Address::updateOrCreate(['user_id' => $customer->id, 'label' => 'Home'], [
            'contact_name' => 'Demo Customer', 'phone' => '08000000003', 'line1' => '7 Sample Crescent', 'city' => 'Yaba', 'state' => 'Lagos', 'service_zone_id' => $zoneModels['LAG-MAIN']->id,
        ]);

        $this->command?->warn('DEMO data seeded. Logins (password: '.self::PASSWORD.'): admin@, dispatcher@, customer@, business@, rider@, rider2@ example.com');
    }
}
