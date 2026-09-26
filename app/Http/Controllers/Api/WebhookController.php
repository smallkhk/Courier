<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    /** Paystack webhook: signature-verified, idempotent, re-verified against the API. */
    public function paystack(Request $request, PaymentService $payments)
    {
        [$status, $outcome] = $payments->handleWebhook('paystack', $request->getContent(), $request->headers->all());

        return response()->json(['received' => $status === 200, 'outcome' => $outcome], $status);
    }
}
