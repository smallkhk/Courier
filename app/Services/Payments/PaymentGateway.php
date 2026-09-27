<?php

namespace App\Services\Payments;

use App\Models\Payment;

interface PaymentGateway
{
    public function name(): string;

    /** Create the checkout session server-side and return the URL to redirect the payer to. */
    public function initialize(Payment $payment, string $callbackUrl): string;

    /**
     * Ask the provider for the authoritative state of a transaction.
     *
     * @return array{status:string, amount_minor:?int, currency:?string, reference:string, transaction_id:?string, channel:?string, paid_at:?string, message:?string}
     *                                                                                                                                                                status: success | failed | abandoned | pending
     */
    public function verify(Payment $payment): array;

    /** Validate a webhook signature against the raw request body. */
    public function verifyWebhook(string $rawBody, array $headers): bool;

    /**
     * Extract what we need from a verified webhook body.
     *
     * @return array{key:string, type:string, reference:?string, settle:bool}
     *                                                                        key: unique event id (for idempotency); settle: whether to re-verify and settle the payment
     */
    public function parseWebhook(string $rawBody): array;

    /** @return array{status:string, reference:?string, message:?string} status: processed | pending | failed */
    public function refund(Payment $payment, int $amountMinor, string $reason): array;
}
