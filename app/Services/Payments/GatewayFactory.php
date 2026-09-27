<?php

namespace App\Services\Payments;

use RuntimeException;

class GatewayFactory
{
    public static function make(?string $provider = null): PaymentGateway
    {
        $provider ??= config('courier.payments.provider');

        return match ($provider) {
            'stripe' => new StripeGateway(
                (string) (config('courier.payments.stripe.secret_key') ?: throw new RuntimeException('STRIPE_SECRET_KEY is not set.')),
                config('courier.payments.stripe.webhook_secret'),
                (string) config('courier.payments.stripe.base_url'),
            ),
            'paystack' => new PaystackGateway(
                (string) (config('courier.payments.paystack.secret_key') ?: throw new RuntimeException('PAYSTACK_SECRET_KEY is not set.')),
                (string) config('courier.payments.paystack.base_url'),
            ),
            'sandbox' => app()->environment('production')
                ? throw new RuntimeException('The sandbox payment gateway cannot be used in production.')
                : new SandboxGateway,
            default => throw new RuntimeException("Unknown payment provider [{$provider}]."),
        };
    }
}
