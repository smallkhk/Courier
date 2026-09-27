<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\BookingController;
use App\Models\Payment;
use App\Models\Shipment;
use App\Services\BookingService;
use App\Services\Notifications\SmsSender;
use App\Services\Payments\StripeGateway;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StripeTest extends TestCase
{
    use RefreshDatabase;

    private const WHSEC = 'whsec_test_not_a_real_secret';

    private Shipment $shipment;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'courier.payments.provider' => 'stripe',
            'courier.payments.stripe.secret_key' => 'test-stripe-key-not-real',
            'courier.payments.stripe.webhook_secret' => self::WHSEC,
        ]);
        $this->seedNetwork();
        $user = $this->user();
        $quote = app(PricingService::class)->quote(BookingController::quoteInput($this->normalizedPayload()), $user->id);
        $this->shipment = app(BookingService::class)->create($this->normalizedPayload(), $quote, $user, null, 'online', 's1')['shipment'];
        $this->actingAs($user);
    }

    private function stripeSession(string $paymentStatus = 'paid', ?int $amount = null, string $currency = 'usd', string $ref = ''): array
    {
        return ['id' => 'cs_test_123', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_123', 'status' => $paymentStatus === 'paid' ? 'complete' : 'open',
            'payment_status' => $paymentStatus, 'amount_total' => $amount ?? 150500, 'currency' => $currency,
            'client_reference_id' => $ref, 'payment_intent' => 'pi_test_123', 'payment_method_types' => ['card']];
    }

    private function signedPost(array $event, ?int $t = null, ?string $secret = null)
    {
        $raw = json_encode($event);
        $t ??= time();
        $sig = hash_hmac('sha256', $t.'.'.$raw, $secret ?? self::WHSEC);

        return $this->call('POST', '/api/webhooks/stripe', [], [], [], ['HTTP_STRIPE_SIGNATURE' => "t={$t},v1={$sig}", 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], $raw);
    }

    private function startCheckout(): Payment
    {
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response($this->stripeSession('unpaid'))]);
        $this->post(route('checkout.pay', $this->shipment))->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_123');
        Http::assertSent(fn ($r) => $r->url() === 'https://api.stripe.com/v1/checkout/sessions'
            && $r['line_items'][0]['price_data']['unit_amount'] === 150500
            && $r['line_items'][0]['price_data']['currency'] === 'usd'
            && $r->hasHeader('Idempotency-Key'));

        return Payment::firstOrFail();
    }

    public function test_checkout_and_signed_webhook_settle_the_payment_once(): void
    {
        $p = $this->startCheckout();
        $this->assertSame('cs_test_123', $p->metadata['stripe_session_id']);

        Http::fake(['api.stripe.com/v1/checkout/sessions/*' => Http::response($this->stripeSession('paid', 150500, 'usd', $p->reference))]);
        $event = ['id' => 'evt_1', 'type' => 'checkout.session.completed', 'data' => ['object' => ['client_reference_id' => $p->reference]]];
        $this->signedPost($event)->assertOk()->assertJson(['outcome' => 'successful']);
        $this->assertSame('successful', $p->fresh()->status);
        $this->assertSame('pi_test_123', $p->fresh()->provider_transaction_id);
        $this->assertSame(ShipmentStatus::Booked, $this->shipment->fresh()->status);

        $this->signedPost($event)->assertOk()->assertJson(['outcome' => 'duplicate']);
    }

    public function test_bad_or_replayed_signatures_are_rejected(): void
    {
        $p = $this->startCheckout();
        $event = ['id' => 'evt_2', 'type' => 'checkout.session.completed', 'data' => ['object' => ['client_reference_id' => $p->reference]]];
        $this->signedPost($event, null, 'whsec_wrong')->assertStatus(401);
        $this->signedPost($event, time() - 3600)->assertStatus(401); // outside the 5-minute tolerance
        $this->assertSame('pending', $p->fresh()->status);
    }

    public function test_amount_mismatch_is_not_accepted(): void
    {
        $p = $this->startCheckout();
        Http::fake(['api.stripe.com/v1/checkout/sessions/*' => Http::response($this->stripeSession('paid', 100, 'usd', $p->reference))]);
        $this->signedPost(['id' => 'evt_3', 'type' => 'checkout.session.completed', 'data' => ['object' => ['client_reference_id' => $p->reference]]])
            ->assertJson(['outcome' => 'mismatch']);
        $this->assertSame(ShipmentStatus::PendingPayment, $this->shipment->fresh()->status);
    }

    public function test_cancelled_checkout_returns_to_payment_page(): void
    {
        $p = $this->startCheckout();
        Http::fake(['api.stripe.com/v1/checkout/sessions/*' => Http::response($this->stripeSession('unpaid', null, 'usd', $p->reference))]);
        $this->get(route('payments.callback', ['reference' => $p->reference, 'cancelled' => 1]))
            ->assertRedirect(route('checkout.show', $this->shipment))->assertSessionHas('status');
        $this->assertSame('pending', $p->fresh()->status);
    }

    public function test_zero_decimal_currency_conversion(): void
    {
        $this->assertSame(1500, StripeGateway::toStripeAmount(150000, 'JPY'));
        $this->assertSame(150000, StripeGateway::fromStripeAmount(1500, 'jpy'));
        $this->assertSame(1999, StripeGateway::toStripeAmount(1999, 'USD'));
    }

    public function test_twilio_sms_driver(): void
    {
        config(['courier.sms.driver' => 'twilio', 'courier.sms.twilio.sid' => 'ACtest', 'courier.sms.twilio.token' => 't', 'courier.sms.twilio.from' => '+12125550100']);
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201)]);
        $r = app(SmsSender::class)->send('(718) 555-0148', 'Hello');
        $this->assertSame(['provider' => 'twilio', 'id' => 'SM123'], $r);
        Http::assertSent(fn ($req) => $req['To'] === '+17185550148' && $req['From'] === '+12125550100');
    }
}
