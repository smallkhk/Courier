<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\BookingController;
use App\Services\BookingService;
use App\Services\PricingService;
use App\Services\ShipmentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingTest extends TestCase
{
    use RefreshDatabase;

    private function shipment()
    {
        $this->seedNetwork();
        $user = $this->user();
        $q = app(PricingService::class)->quote(BookingController::quoteInput($this->normalizedPayload()), $user->id);

        return app(BookingService::class)->create($this->normalizedPayload(), $q, $user, null, 'online', 'trk')['shipment'];
    }

    public function test_public_tracking_shows_timeline_but_not_private_data(): void
    {
        $s = $this->shipment();
        $admin = $this->user('admin');
        app(ShipmentWorkflow::class)->transition($s, ShipmentStatus::Booked, $admin, ['role' => 'admin', 'internal_note' => 'SECRET-INTERNAL-NOTE']);

        $res = $this->get(route('track.show', $s->tracking_number))->assertOk();
        $res->assertSee($s->tracking_number)->assertSee('Booked')->assertSee('Brooklyn');
        $res->assertDontSee('SECRET-INTERNAL-NOTE')->assertDontSee('2 Test Close')->assertDontSee('+17185550148')->assertDontSee('555-0148')->assertDontSee('ada@example.com');

        $json = $this->getJson('/api/track/'.$s->tracking_number)->assertOk()->json();
        $this->assertCount(2, $json['events']);
        $this->assertStringNotContainsString('SECRET', json_encode($json));
        $this->assertStringNotContainsString('5550148', json_encode($json));
    }

    public function test_unknown_and_malformed_numbers_look_the_same(): void
    {
        $this->get('/track/NOPE')->assertNotFound();
        $this->get('/track/CX0000000000000')->assertNotFound();
        $this->getJson('/api/track/CX0000000000000')->assertNotFound();
    }

    public function test_verification_reveals_recipient_details(): void
    {
        $s = $this->shipment();
        $this->post(route('track.verify', $s->tracking_number), ['phone_last4' => '0000'])->assertSessionHasErrors('phone_last4');
        $this->post(route('track.verify', $s->tracking_number), ['phone_last4' => '0148'])->assertRedirect();
        $this->get(route('track.show', $s->tracking_number))->assertSee('Bola Recipient');
    }

    public function test_tracking_is_rate_limited(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->get('/track/CX0000000000000');
        }
        $this->get('/track/CX0000000000000')->assertStatus(429);
    }
}
