<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Support\Money;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Paystack Standard (redirect) integration.
 * Docs: https://paystack.com/docs/payments/accept-payments/  (initialize, verify, webhooks, refunds)
 */
class PaystackGateway implements PaymentGateway
{
    public function __construct(private string $secretKey, private string $baseUrl) {}

    public function name(): string
    {
        return 'paystack';
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/'))->withToken($this->secretKey)->acceptJson()->timeout(20);
    }

    public function initialize(Payment $payment, string $callbackUrl): string
    {
        $res = $this->http()->post('/transaction/initialize', [
            'email' => $payment->payer_email,
            'amount' => Money::toMinor($payment->amount), // smallest currency unit
            'currency' => $payment->currency,
            'reference' => $payment->reference,
            'callback_url' => $callbackUrl,
            'metadata' => ['payment_id' => $payment->id, 'shipments' => $payment->shipments()->pluck('tracking_number')->all()],
        ]);
        if (! $res->successful() || ! $res->json('status')) {
            throw new RuntimeException('Payment provider could not start checkout: '.($res->json('message') ?? 'HTTP '.$res->status()));
        }

        return (string) $res->json('data.authorization_url');
    }

    public function verify(Payment $payment): array
    {
        $res = $this->http()->get('/transaction/verify/'.rawurlencode($payment->reference));
        if (! $res->successful()) {
            throw new RuntimeException('Could not verify payment with provider (HTTP '.$res->status().').');
        }
        $d = $res->json('data') ?? [];
        $status = match ($d['status'] ?? null) {
            'success' => 'success',
            'failed', 'reversed' => 'failed',
            'abandoned' => 'abandoned',
            default => 'pending',
        };

        return [
            'status' => $status,
            'amount_minor' => isset($d['amount']) ? (int) $d['amount'] : null,
            'currency' => $d['currency'] ?? null,
            'reference' => (string) ($d['reference'] ?? ''),
            'transaction_id' => isset($d['id']) ? (string) $d['id'] : null,
            'channel' => $d['channel'] ?? null,
            'paid_at' => $d['paid_at'] ?? null,
            'message' => $d['gateway_response'] ?? null,
        ];
    }

    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        $sig = $headers['x-paystack-signature'][0] ?? $headers['x-paystack-signature'] ?? null;
        if (! is_string($sig) || $sig === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $rawBody, $this->secretKey), $sig);
    }

    public function parseWebhook(string $rawBody): array
    {
        $p = json_decode($rawBody, true) ?: [];
        $type = (string) ($p['event'] ?? 'unknown');

        return [
            'key' => hash('sha256', $rawBody),
            'type' => $type,
            'reference' => $p['data']['reference'] ?? null,
            'settle' => in_array($type, ['charge.success', 'charge.failed'], true),
        ];
    }

    public function refund(Payment $payment, int $amountMinor, string $reason): array
    {
        $res = $this->http()->post('/refund', [
            'transaction' => $payment->reference,
            'amount' => $amountMinor,
            'merchant_note' => mb_substr($reason, 0, 200),
        ]);
        if (! $res->successful() || ! $res->json('status')) {
            return ['status' => 'failed', 'reference' => null, 'message' => $res->json('message') ?? 'HTTP '.$res->status()];
        }
        $status = $res->json('data.status');

        return ['status' => $status === 'processed' ? 'processed' : 'pending', 'reference' => (string) $res->json('data.id'), 'message' => null];
    }
}
