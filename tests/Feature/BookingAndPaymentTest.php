<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Models\Payment;
use App\Models\Shipment;
use App\Services\Payments\GatewayFactory;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class BookingAndPaymentTest extends TestCase
{
    use RefreshDatabase;

    /** Walk the booking wizard; returns the created shipment. */
    private function book($as = null, array $payload = []): Shipment
    {
        $client = $as ? $this->actingAs($as) : $this;
        $client->post(route('book.quote'), $this->bookingPayload($payload))->assertRedirect(route('book.review'));
        $key = session('booking.idempotency');
        $this->get(route('book.review'))->assertOk()->assertSee('Confirm booking');
        $this->post(route('book.confirm'), ['accept_terms' => '1', 'payment_method' => 'online', 'idempotency_key' => $key, 'total' => '1.00'])->assertRedirect();

        return Shipment::where('idempotency_key', $key)->firstOrFail();
    }

    private function paySandbox(Shipment $s, string $outcome): Payment
    {
        $this->post(route('checkout.pay', $s))->assertRedirect();
        $p = $s->payments()->latest('payments.id')->first();
        $this->post(URL::temporarySignedRoute('sandbox.complete', now()->addHour(), ['reference' => $p->reference]), ['outcome' => $outcome])
            ->assertRedirect(route('payments.callback', ['reference' => $p->reference]));
        $this->get(route('payments.callback', ['reference' => $p->reference]))->assertRedirect();

        return $p->fresh();
    }

    public function test_customer_books_and_pays_in_sandbox(): void
    {
        $this->seedNetwork();
        $user = $this->user();
        $s = $this->book($user);

        $this->assertSame(ShipmentStatus::PendingPayment, $s->status);
        $this->assertSame('1505.00', $s->total, 'Client-submitted totals are ignored; price comes from the server quote.');
        $this->assertSame($user->id, $s->user_id);
        $this->assertNotNull($s->estimated_delivery_to);

        $p = $this->paySandbox($s, 'success');
        $this->assertSame('successful', $p->status);
        $this->assertSame(ShipmentStatus::Booked, $s->fresh()->status);
        $this->assertDatabaseHas('notification_logs', ['shipment_id' => $s->id, 'event' => 'payment_confirmed']);
        $this->assertDatabaseHas('notification_logs', ['shipment_id' => $s->id, 'event' => 'booking_created']);

        // Paying again is refused; no second charge.
        $this->post(route('checkout.pay', $s))->assertRedirect(route('book.confirmation', $s));
        $this->assertSame(1, Payment::count());
    }

    public function test_double_submit_creates_one_shipment(): void
    {
        $this->seedNetwork();
        $user = $this->user();
        $this->actingAs($user)->post(route('book.quote'), $this->bookingPayload());
        $key = session('booking.idempotency');
        $this->post(route('book.confirm'), ['accept_terms' => '1', 'payment_method' => 'online', 'idempotency_key' => $key]);
        $this->post(route('book.confirm'), ['accept_terms' => '1', 'payment_method' => 'online', 'idempotency_key' => $key])->assertRedirect();
        $this->assertSame(1, Shipment::count());
    }

    public function test_failed_payment_keeps_shipment_recoverable(): void
    {
        $this->seedNetwork();
        $s = $this->book($this->user());
        $p = $this->paySandbox($s, 'failed');
        $this->assertSame('failed', $p->status);
        $this->assertSame(ShipmentStatus::PendingPayment, $s->fresh()->status);
        $this->assertDatabaseHas('notification_logs', ['shipment_id' => $s->id, 'event' => 'payment_failed']);

        // Retry succeeds with a new payment reference.
        $p2 = $this->paySandbox($s, 'success');
        $this->assertNotSame($p->reference, $p2->reference);
        $this->assertSame(ShipmentStatus::Booked, $s->fresh()->status);
    }

    public function test_guest_booking_and_guest_access(): void
    {
        $this->seedNetwork();
        $s = $this->book();
        $this->assertNull($s->user_id);
        $this->get(route('checkout.show', $s))->assertOk(); // same session holds the guest token

        // A different browser without the token cannot see the checkout.
        $this->flushSession();
        $this->get(route('checkout.show', $s))->assertNotFound();
    }

    public function test_guest_booking_can_be_disabled(): void
    {
        $this->seedNetwork();
        Settings::set('guest_booking_enabled', false);
        $this->get(route('book.start'))->assertRedirect(route('login'));
    }

    public function test_address_outside_zone_is_rejected(): void
    {
        $this->seedNetwork();
        // Beverly Hills, CA 90210 is not in any test zone.
        $this->actingAs($this->user())->post(route('book.quote'), $this->bookingPayload(['delivery_city' => 'Beverly Hills', 'delivery_region' => 'CA', 'delivery_postal_code' => '90210']))
            ->assertSessionHasErrors('delivery_address');
    }

    public function test_customer_cannot_view_someone_elses_checkout(): void
    {
        $this->seedNetwork();
        $s = $this->book($this->user());
        $this->actingAs($this->user())->get(route('checkout.show', $s))->assertNotFound();
    }

    public function test_sandbox_gateway_is_refused_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(\RuntimeException::class);
        GatewayFactory::make('sandbox');
    }
}
