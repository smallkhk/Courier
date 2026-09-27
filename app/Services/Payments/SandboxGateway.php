<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Support\Money;
use Illuminate\Support\Facades\URL;

/**
 * DEVELOPMENT-ONLY gateway. No money moves. The checkout page is clearly labelled
 * "SANDBOX" and lets the tester choose success, failure or abandon. Verification
 * reads the stored simulated outcome so the rest of the payment pipeline
 * (amount/currency/reference checks, idempotency, status transitions) runs
 * exactly as it would with a real provider. Refused when APP_ENV=production.
 */
class SandboxGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'sandbox';
    }

    public function initialize(Payment $payment, string $callbackUrl): string
    {
        return URL::temporarySignedRoute('sandbox.checkout', now()->addHour(), ['reference' => $payment->reference]);
    }

    public function verify(Payment $payment): array
    {
        $outcome = $payment->metadata['sandbox_outcome'] ?? null;

        return [
            'status' => match ($outcome) {
                'success' => 'success',
                'failed' => 'failed',
                'abandoned' => 'abandoned',
                default => 'pending',
            },
            'amount_minor' => $outcome ? Money::toMinor($payment->amount) : null,
            'currency' => $payment->currency,
            'reference' => $payment->reference,
            'transaction_id' => $outcome ? 'SBX-'.$payment->id : null,
            'channel' => 'sandbox',
            'paid_at' => $outcome === 'success' ? now()->toIso8601String() : null,
            'message' => $outcome === 'failed' ? 'Simulated decline (sandbox)' : null,
        ];
    }

    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        return false; // The sandbox never receives webhooks.
    }

    public function parseWebhook(string $rawBody): array
    {
        return ['key' => hash('sha256', $rawBody), 'type' => 'none', 'reference' => null, 'settle' => false];
    }

    public function refund(Payment $payment, int $amountMinor, string $reason): array
    {
        return ['status' => 'processed', 'reference' => 'SBX-RF-'.$payment->id.'-'.time(), 'message' => 'Sandbox refund (no money moved)'];
    }
}
