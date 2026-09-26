<?php

namespace Tests\Feature;

use App\Http\Controllers\BookingController;
use App\Models\Address;
use App\Models\BulkImport;
use App\Models\BusinessMember;
use App\Models\Shipment;
use App\Services\BookingService;
use App\Services\BulkImportService;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BusinessTest extends TestCase
{
    use RefreshDatabase;

    private function shipmentFor($business, $user, string $key): Shipment
    {
        $q = app(PricingService::class)->quote(BookingController::quoteInput($this->bookingPayload()), $user->id, $business);

        return app(BookingService::class)->create($this->bookingPayload(), $q, $user, $business, 'online', $key)['shipment'];
    }

    public function test_businesses_cannot_see_each_others_data(): void
    {
        $this->seedNetwork();
        [$a, $ua] = $this->businessWith();
        [$b, $ub] = $this->businessWith();
        $sa = $this->shipmentFor($a, $ua, 'a1');
        $sb = $this->shipmentFor($b, $ub, 'b1');

        $this->actingAs($ua)->get(route('business.shipments.show', $sb))->assertNotFound();
        $this->actingAs($ua)->getJson(route('api.shipments.show', $sb))->assertNotFound();
        $this->actingAs($ua)->get(route('business.shipments.index'))->assertSee($sa->tracking_number)->assertDontSee($sb->tracking_number);
        $csv = $this->actingAs($ua)->get(route('business.shipments.export'))->streamedContent();
        $this->assertStringContainsString($sa->tracking_number, $csv);
        $this->assertStringNotContainsString($sb->tracking_number, $csv);
        $ids = collect($this->actingAs($ua)->getJson('/api/shipments')->json('data'))->pluck('tracking_number');
        $this->assertTrue($ids->contains($sa->tracking_number));
        $this->assertFalse($ids->contains($sb->tracking_number));
    }

    public function test_team_permissions(): void
    {
        $this->seedNetwork();
        [$biz, $owner] = $this->businessWith();
        $shipper = $this->user();
        BusinessMember::create(['business_id' => $biz->id, 'user_id' => $shipper->id, 'role' => 'shipper']);
        $viewer = $this->user();
        BusinessMember::create(['business_id' => $biz->id, 'user_id' => $viewer->id, 'role' => 'viewer']);

        $ownerShipment = $this->shipmentFor($biz, $owner, 'o1');
        $this->actingAs($shipper)->get(route('business.shipments.show', $ownerShipment))->assertNotFound(); // shippers see only their own
        $this->actingAs($viewer)->get(route('business.shipments.show', $ownerShipment))->assertOk();
        $this->actingAs($viewer)->get(route('business.bulk.create'))->assertForbidden();
        $this->actingAs($viewer)->get(route('business.team.index'))->assertForbidden();
        $this->actingAs($shipper)->get(route('business.invoices.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('business.team.index'))->assertOk();
    }

    public function test_pending_business_cannot_ship_as_business(): void
    {
        $this->seedNetwork();
        [, $u] = $this->businessWith('owner', ['status' => 'pending']);
        $this->actingAs($u)->get(route('business.dashboard'))->assertOk()->assertSee('being reviewed');
        $this->actingAs($u)->get(route('business.shipments.index'))->assertForbidden();
    }

    public function test_bulk_import_preview_and_confirm(): void
    {
        $this->seedNetwork();
        [$biz, $owner] = $this->businessWith();
        $pickup = Address::forceCreate(['business_id' => $biz->id, 'label' => 'WH', 'contact_name' => 'Desk', 'phone' => '0803', 'line1' => 'x', 'city' => 'Ikeja', 'state' => 'Lagos', 'service_zone_id' => $this->origin->id]);
        $csv = implode(',', BulkImportService::COLUMNS)."\n"
            ."Ada,08030000001,,1 A St,Lekki,Lagos,T-B,EXP,Shoes,clothing,1.5,,,,0,no,,R1\n"
            ."Ada,08030000001,,1 A St,Lekki,Lagos,T-B,EXP,Shoes,clothing,1.5,,,,0,no,,R1\n"   // duplicate
            ."Bad,08030000002,,2 B St,Kano,Kano,T-B,EXP,Box,other,2,,,,0,no,,R2\n"            // city not in zone
            ."Cy,08030000003,,3 C St,Lekki,Lagos,T-B,NOPE,Box,other,2,,,,0,no,,R3\n";        // unknown service
        $file = UploadedFile::fake()->createWithContent('ship.csv', $csv);

        $res = $this->actingAs($owner)->post(route('business.bulk.preview'), ['file' => $file, 'pickup_address_id' => $pickup->id]);
        $import = BulkImport::firstOrFail();
        $res->assertRedirect(route('business.bulk.show', $import->id));
        $this->assertSame(1, $import->valid_count);
        $this->assertSame(3, $import->error_count);
        $this->assertSame(0, Shipment::count(), 'Nothing is created before confirmation.');

        $this->actingAs($owner)->post(route('business.bulk.confirm', $import->id))->assertRedirect();
        $this->assertSame(1, Shipment::where('business_id', $biz->id)->count());
        // Confirming twice does not duplicate.
        $this->actingAs($owner)->post(route('business.bulk.confirm', $import->id))->assertSessionHasErrors('import');
        $this->assertSame(1, Shipment::count());

        // Another business can't open this import.
        [, $other] = $this->businessWith();
        $this->actingAs($other)->get(route('business.bulk.show', $import->id))->assertNotFound();
    }

    public function test_invoice_terms_business_is_billed_by_invoice(): void
    {
        $this->seedNetwork();
        [$biz, $owner] = $this->businessWith('owner', ['payment_terms' => 'invoice']);
        $s = $this->shipmentFor($biz, $owner, 'inv1');
        $this->assertSame('invoice', $s->payment_method);
        $this->assertSame('booked', $s->status->value);
        $this->actingAs($this->user('admin'))->post(route('ops.businesses.invoice', $biz), ['from' => now()->subDay()->toDateString(), 'to' => now()->toDateString()])->assertSessionHasNoErrors();
        $this->assertNotNull($s->fresh()->invoice_id);
        $this->actingAs($owner)->get(route('business.invoices.index'))->assertSee('INV-');
    }
}
