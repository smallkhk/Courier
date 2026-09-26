<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Shipment;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /** Initialise (or resume) payment; returns the provider checkout URL. */
    public function store(Request $request, Shipment $shipment, PaymentService $payments)
    {
        abort_unless(Shipment::visibleTo($request->user())->whereKey($shipment->id)->exists(), 404);
        $payment = $payments->start(collect([$shipment]), $request->user()->email, $request->user());

        return response()->json(['reference' => $payment->reference, 'status' => $payment->status, 'checkout_url' => $payment->checkout_url, 'amount' => $payment->amount, 'currency' => $payment->currency], 201);
    }

    public function show(Request $request, Payment $payment)
    {
        $user = $request->user();
        $allowed = $user->isStaff() || $payment->user_id === $user->id
            || ($payment->business_id && $user->membershipFor($payment->business_id)?->can('view_invoices'));
        abort_unless($allowed, 404);

        return response()->json([
            'reference' => $payment->reference, 'status' => $payment->status, 'amount' => $payment->amount, 'currency' => $payment->currency,
            'provider' => $payment->provider, 'paid_at' => $payment->paid_at?->toIso8601String(), 'failure_reason' => $payment->failure_reason,
            'shipments' => $payment->shipments()->pluck('tracking_number'),
        ]);
    }
}
