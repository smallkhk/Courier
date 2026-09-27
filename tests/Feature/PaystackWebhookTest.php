<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\BookingController;
use App\Models\NotificationLog;
use App\Models\Payment;
use App\Models\Shipment;
use App\Services\BookingService;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaystackWebhookTest extends TestCase
{
    use RefreshDatabase;

    private Payment $payment;

    private Shipment $shipment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedNetwork();
        $user = $this->user();
        $quote = app(PricingService::class)->quote(BookingController::quoteInput($this->normalizedPayload()), $user->id);
        $this->shipment = app(BookingService::class)->create($this->normalizedPayload(), $quote, $user, null, 'online', 'k1')['shipment'];
        $this->payment = Payment::create([
            'user_id' => $user->id, 'provider' => 'paystack', 'reference' => 'PAY-TEST-1', 'amount' => $this->shipment->total,
            'currency' => 'NGN', 'status' => 'pending', 'payer_email' => $user->email, 'expires_at' => now()->addHour(),
        ]);
        $this->payment->shipments()->attach($this->shipment->id, ['amount' => $this->shipment->total]);
    }

    private function send(array $payload, ?string $sig = null)
    {
        $raw = json_encode($payload);
        $sig ??= hash_hmac('sha512', $raw, config('courier.payments.paystack.secret_key'));

        return $this->call('POST', '/api/webhooks/paystack', [], [], [], ['HTTP_X_PAYSTACK_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], $raw);
    }

    private function fakeVerify(int $amountMinor, string $status = 'success', string $currency = 'NGN'): void
    {
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => [
            'status' => $status, 'amount' => $amountMinor, 'currency' => $currency, 'reference' => 'PAY-TEST-1', 'id' => 99, 'channel' => 'card', 'paid_at' => now()->toIso8601String(),
        ]])]);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $this->send(['event' => 'charge.success', 'data' => ['reference' => 'PAY-TEST-1']], 'bad')->assertStatus(401);
        $this->assertSame('pending', $this->payment->fresh()->status);
    }

    public function test_valid_webhook_is_reverified_and_idempotent(): void
    {
        $this->fakeVerify(150500);
        $payload = ['event' => 'charge.success', 'data' => ['reference' => 'PAY-TEST-1', 'amount' => 150500]];
        $this->send($payload)->assertOk()->assertJson(['outcome' => 'successful']);
        $this->assertSame('successful', $this->payment->fresh()->status);
        $this->assertSame(ShipmentStatus::Booked, $this->shipment->fresh()->status);
        Http::assertSentCount(1);

        // Duplicate delivery of the same event does nothing.
        $this->send($payload)->assertOk()->assertJson(['outcome' => 'duplicate']);
        $this->assertSame(1, $this->shipment->events()->where('status', 'booked')->count());
        $this->assertSame(1, NotificationLog::where('event', 'payment_confirmed')->where('channel', 'email')->count());
    }

    public function test_amount_mismatch_is_not_accepted(): void
    {
        $this->fakeVerify(100); // provider says only 1.00 was paid
        $this->send(['event' => 'charge.success', 'data' => ['reference' => 'PAY-TEST-1']])->assertOk()->assertJson(['outcome' => 'mismatch']);
        $this->assertSame('failed', $this->payment->fresh()->status);
        $this->assertSame(ShipmentStatus::PendingPayment, $this->shipment->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.verification_mismatch']);
    }

    public function test_currency_mismatch_is_not_accepted(): void
    {
        $this->fakeVerify(150500, 'success', 'USD');
        $this->send(['event' => 'charge.success', 'data' => ['reference' => 'PAY-TEST-1']])->assertJson(['outcome' => 'mismatch']);
        $this->assertSame(ShipmentStatus::PendingPayment, $this->shipment->fresh()->status);
    }

    public function test_webhook_body_alone_is_never_trusted(): void
    {
        $this->fakeVerify(150500, 'abandoned');
        $this->send(['event' => 'charge.success', 'data' => ['reference' => 'PAY-TEST-1', 'status' => 'success']]);
        $this->assertNotSame('successful', $this->payment->fresh()->status);
    }
}
