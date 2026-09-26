<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $q = Payment::with('shipments:id,tracking_number', 'user:id,name', 'business:id,name')->latest();
        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }
        if ($request->filled('q')) {
            $q->where('reference', 'like', '%'.$request->string('q').'%');
        }

        return view('ops.payments.index', ['payments' => $q->paginate(30)->withQueryString()]);
    }

    public function show(Payment $payment)
    {
        return view('ops.payments.show', ['p' => $payment->load('shipments', 'refunds.requester', 'user', 'business')]);
    }

    /** Re-check with the provider (e.g. customer says they paid). */
    public function verify(Payment $payment, PaymentService $payments)
    {
        try {
            $outcome = $payments->confirm($payment->reference, 'staff');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Could not reach the payment provider: '.$e->getMessage());
        }

        return back()->with('status', "Verification result: {$outcome}.");
    }

    public function refund(Request $request, Payment $payment, PaymentService $payments)
    {
        $data = $request->validate(['amount' => 'required|numeric|min:0.01', 'reason' => 'required|string|max:250']);
        $refund = $payments->refund($payment, (string) $data['amount'], $data['reason'], $request->user());

        return back()->with($refund->status === 'failed' ? 'error' : 'success', 'Refund '.$refund->status.($refund->failure_reason ? ': '.$refund->failure_reason : '.'));
    }
}
