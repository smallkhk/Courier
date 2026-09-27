<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    /** Stripe / Paystack webhooks: signature-verified, idempotent, re-verified against the provider API. */
    public function handle(Request $request, string $provider, PaymentService $payments)
    {
        [$status, $outcome] = $payments->handleWebhook($provider, $request->getContent(), $request->headers->all());

        return response()->json(['received' => $status === 200, 'outcome' => $outcome], $status);
    }
}
