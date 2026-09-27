<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Support\Money;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Stripe Checkout (hosted payment page: cards, Apple Pay, Google Pay, Link, and any
 * other methods enabled in the Stripe Dashboard).
 * Docs: https://docs.stripe.com/payments/checkout · https://docs.stripe.com/webhooks
 */
class StripeGateway implements PaymentGateway
{
    /** Currencies Stripe expects in whole units (no cents). */
    private const ZERO_DECIMAL = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];

    public const SIGNATURE_TOLERANCE = 300;

    public function __construct(private string $secretKey, private ?string $webhookSecret, private string $baseUrl = 'https://api.stripe.com') {}

    public function name(): string
    {
        return 'stripe';
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/'))->withToken($this->secretKey)->asForm()->acceptJson()->timeout(20);
    }

    /** Our 2-decimal minor units → Stripe's smallest currency unit. */
    public static function toStripeAmount(int $minor, string $currency): int
    {
        return in_array(strtoupper($currency), self::ZERO_DECIMAL, true) ? intdiv($minor, 100) : $minor;
    }

    public static function fromStripeAmount(int $amount, string $currency): int
    {
        return in_array(strtoupper($currency), self::ZERO_DECIMAL, true) ? $amount * 100 : $amount;
    }

    /** Stripe requires expiry between 30 minutes and 24 hours from now. */
    public static function sessionExpiry(Payment $payment): Carbon
    {
        $min = now()->addMinutes(31);
        $max = now()->addHours(23);
        $want = $payment->expires_at ?? now()->addHour();

        return $want->lt($min) ? $min : ($want->gt($max) ? $max : $want);
    }

    public function initialize(Payment $payment, string $callbackUrl): string
    {
        $return = $callbackUrl.(str_contains($callbackUrl, '?') ? '&' : '?').'reference='.rawurlencode($payment->reference);
        $items = $payment->shipments()->pluck('tracking_number')->all();
        $res = $this->http()->withHeaders(['Idempotency-Key' => 'checkout-'.$payment->reference])->post('/v1/checkout/sessions', [
            'mode' => 'payment',
            'client_reference_id' => $payment->reference,
            'customer_email' => $payment->payer_email,
            'success_url' => $return,
            'cancel_url' => $return.'&cancelled=1',
            'expires_at' => self::sessionExpiry($payment)->timestamp,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($payment->currency),
                    'unit_amount' => self::toStripeAmount(Money::toMinor($payment->amount), $payment->currency),
                    'product_data' => ['name' => $items ? 'Shipment '.implode(', ', array_slice($items, 0, 5)).(count($items) > 5 ? '…' : '') : 'Invoice payment'],
                ],
            ]],
            'metadata' => ['reference' => $payment->reference, 'payment_id' => $payment->id],
            'payment_intent_data' => ['metadata' => ['reference' => $payment->reference]],
        ]);
        if (! $res->successful()) {
            throw new RuntimeException('Stripe could not start checkout: '.($res->json('error.message') ?? 'HTTP '.$res->status()));
        }
        $payment->metadata = array_merge($payment->metadata ?? [], ['stripe_session_id' => $res->json('id')]);

        return (string) $res->json('url');
    }

    public function verify(Payment $payment): array
    {
        $sessionId = $payment->metadata['stripe_session_id'] ?? null;
        $base = ['status' => 'pending', 'amount_minor' => null, 'currency' => null, 'reference' => $payment->reference, 'transaction_id' => null, 'channel' => null, 'paid_at' => null, 'message' => null];
        if (! $sessionId) {
            return $base;
        }
        $res = $this->http()->get('/v1/checkout/sessions/'.rawurlencode($sessionId));
        if (! $res->successful()) {
            throw new RuntimeException('Could not verify payment with Stripe (HTTP '.$res->status().').');
        }
        $s = $res->json();
        $currency = strtoupper((string) ($s['currency'] ?? ''));
        $status = match (true) {
            ($s['payment_status'] ?? null) === 'paid' => 'success',
            ($s['status'] ?? null) === 'expired' => 'abandoned',
            default => 'pending', // open, or complete with an async method still processing
        };

        return [
            'status' => $status,
            'amount_minor' => isset($s['amount_total']) ? self::fromStripeAmount((int) $s['amount_total'], $currency) : null,
            'currency' => $currency ?: null,
            'reference' => (string) ($s['client_reference_id'] ?? ''),
            'transaction_id' => is_string($s['payment_intent'] ?? null) ? $s['payment_intent'] : null,
            'channel' => implode(',', $s['payment_method_types'] ?? []) ?: 'stripe',
            'paid_at' => $status === 'success' ? now()->toIso8601String() : null,
            'message' => $status === 'abandoned' ? 'Checkout expired' : null,
        ];
    }

    /** Stripe-Signature: t=<unix>,v1=<hex hmac-sha256 of "t.payload"> (possibly several v1 values). */
    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        $header = $headers['stripe-signature'][0] ?? $headers['stripe-signature'] ?? null;
        if (! $this->webhookSecret || ! is_string($header)) {
            return false;
        }
        $t = null;
        $sigs = [];
        foreach (explode(',', $header) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($k === 't') {
                $t = $v;
            } elseif ($k === 'v1') {
                $sigs[] = $v;
            }
        }
        if (! $t || ! ctype_digit($t) || abs(time() - (int) $t) > self::SIGNATURE_TOLERANCE || ! $sigs) {
            return false;
        }
        $expected = hash_hmac('sha256', $t.'.'.$rawBody, $this->webhookSecret);
        foreach ($sigs as $sig) {
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }

        return false;
    }

    public function parseWebhook(string $rawBody): array
    {
        $e = json_decode($rawBody, true) ?: [];
        $type = (string) ($e['type'] ?? 'unknown');
        $obj = $e['data']['object'] ?? [];

        return [
            'key' => (string) ($e['id'] ?? hash('sha256', $rawBody)),
            'type' => $type,
            'reference' => $obj['client_reference_id'] ?? ($obj['metadata']['reference'] ?? null),
            'settle' => in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded', 'checkout.session.async_payment_failed', 'checkout.session.expired'], true),
        ];
    }

    public function refund(Payment $payment, int $amountMinor, string $reason): array
    {
        if (! $payment->provider_transaction_id) {
            return ['status' => 'failed', 'reference' => null, 'message' => 'No Stripe payment intent recorded for this payment.'];
        }
        $res = $this->http()->withHeaders(['Idempotency-Key' => 'refund-'.$payment->reference.'-'.$amountMinor.'-'.$payment->refunds()->count()])->post('/v1/refunds', [
            'payment_intent' => $payment->provider_transaction_id,
            'amount' => self::toStripeAmount($amountMinor, $payment->currency),
            'metadata' => ['reason' => mb_substr($reason, 0, 450), 'reference' => $payment->reference],
        ]);
        if (! $res->successful()) {
            return ['status' => 'failed', 'reference' => null, 'message' => $res->json('error.message') ?? 'HTTP '.$res->status()];
        }

        return [
            'status' => match ($res->json('status')) {
                'succeeded' => 'processed', 'failed', 'canceled' => 'failed', default => 'pending'
            },
            'reference' => (string) $res->json('id'),
            'message' => null,
        ];
    }
}
