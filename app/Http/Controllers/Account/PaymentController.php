<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        return view('account.payments.index', [
            'payments' => Payment::where('user_id', $request->user()->id)->whereNull('business_id')->with('shipments:id,tracking_number')->latest()->paginate(20),
        ]);
    }

    /** Printable receipt (only for verified payments). */
    public function show(Request $request, Payment $payment)
    {
        abort_unless($payment->user_id === $request->user()->id, 404);

        return view('account.payments.receipt', ['payment' => $payment->load('shipments.service', 'refunds')]);
    }
}
