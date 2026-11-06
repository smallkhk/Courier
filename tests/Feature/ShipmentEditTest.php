<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Shipment;
use App\Services\BookingService;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShipmentEditTest extends TestCase
{
    use RefreshDatabase;

    private function booked(): Shipment
    {
        $this->seedNetwork();
        $d = $this->normalizedPayload();
        $quote = app(PricingService::class)->quote($d + ['parcels' => $d['parcels']]);
        $s = app(BookingService::class)->create($d, $quote, $this->user('customer'), null, 'online', (string) Str::uuid())['shipment'];

        return $s->fresh();
    }

    private function form(Shipment $s, array $o = []): array
    {
        $f = ['reason' => 'Customer called', 'package_description' => $s->package_description];
        foreach (['sender', 'recipient'] as $w) {
            foreach (['name', 'phone', 'email'] as $c) {
                $f["{$w}_{$c}"] = $s->{"{$w}_{$c}"};
            }
        }
        foreach (['pickup', 'delivery'] as $side) {
            foreach (['address', 'address2', 'city', 'region', 'postal_code', 'country', 'instructions'] as $c) {
                $f["{$side}_{$c}"] = $s->{"{$side}_{$c}"};
            }
        }

        return array_merge($f, $o);
    }

    public function test_staff_correct_recipient_details_and_it_is_recorded(): void
    {
        $s = $this->booked();
        $total = (string) $s->total;
        $ops = $this->user('dispatcher');
        $this->actingAs($ops)->get(route('ops.shipments.show', $s))->assertSee('Edit details');
        $this->actingAs($ops)->get(route('ops.shipments.edit', $s))->assertOk()->assertSee('Swap sender');

        $this->actingAs($ops)->put(route('ops.shipments.update', $s), $this->form($s, [
            'recipient_name' => 'Correct Name', 'recipient_phone' => '(718) 555-0199', 'delivery_address' => '99 Fixed Street',
        ]))->assertRedirect(route('ops.shipments.show', $s))->assertSessionHas('success');

        $s->refresh();
        $this->assertSame(['Correct Name', '+17185550199', '99 Fixed Street'], [$s->recipient_name, $s->recipient_phone, $s->delivery_address]);
        $this->assertSame($total, (string) $s->total, 'price is never changed by an edit');
        $this->assertNull($s->delivery_lat);
        $log = AuditLog::where('action', 'shipment.details_edited')->firstOrFail();
        $this->assertContains('recipient_phone', $log->metadata['changed']);
        $this->assertStringContainsString('Customer called', $s->events()->reorder('id', 'desc')->first()->internal_note);
    }

    public function test_swap_sender_and_recipient(): void
    {
        $s = $this->booked();
        [$sn, $rn, $pa, $da, $oz, $dz] = [$s->sender_name, $s->recipient_name, $s->pickup_address, $s->delivery_address, $s->origin_zone_id, $s->destination_zone_id];
        $this->actingAs($this->user('admin'))->post(route('ops.shipments.swap', $s), ['reason' => 'Entered the wrong way round'])->assertSessionHas('success');
        $s->refresh();
        $this->assertSame([$rn, $sn, $da, $pa, $dz, $oz], [$s->sender_name, $s->recipient_name, $s->pickup_address, $s->delivery_address, $s->origin_zone_id, $s->destination_zone_id]);
        $this->assertNotNull($s->sender_email, 'sender email is kept when the recipient had none');
    }

    public function test_invalid_or_uncovered_changes_are_refused(): void
    {
        $s = $this->booked();
        $ops = $this->user('dispatcher');
        $this->actingAs($ops)->put(route('ops.shipments.update', $s), $this->form($s, ['recipient_phone' => '123']))->assertSessionHasErrors('recipient_phone');
        $this->actingAs($ops)->put(route('ops.shipments.update', $s), $this->form($s, ['delivery_postal_code' => '90210', 'delivery_city' => 'LA', 'delivery_region' => 'CA']))
            ->assertSessionHasErrors('delivery_address');
        $this->actingAs($ops)->put(route('ops.shipments.update', $s), $this->form($s, ['delivery_country' => 'GB', 'delivery_region' => '', 'delivery_postal_code' => 'SW1A 1AA']))
            ->assertSessionHasErrors('delivery_country');
        $this->assertSame('Bola Recipient', $s->fresh()->recipient_name);
    }

    public function test_customers_and_riders_cannot_edit_and_delivered_is_locked(): void
    {
        $s = $this->booked();
        $this->actingAs($this->user('customer'))->get(route('ops.shipments.edit', $s))->assertForbidden();
        $this->actingAs($this->user('rider'))->post(route('ops.shipments.swap', $s), ['reason' => 'x'])->assertForbidden();
        $s->forceFill(['status' => 'delivered'])->save();
        $this->actingAs($this->user('admin'))->get(route('ops.shipments.edit', $s))->assertStatus(422);
    }
}
