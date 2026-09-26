<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $bid = $request->attributes->get('membership')->business_id;

        return view('business.invoices.index', [
            'invoices' => Invoice::where('business_id', $bid)->latest('issued_at')->paginate(20, ['*'], 'ipage'),
            'payments' => Payment::where('business_id', $bid)->with('shipments:id,tracking_number')->latest()->paginate(20, ['*'], 'ppage'),
        ]);
    }

    public function show(Request $request, Invoice $invoice)
    {
        abort_unless($invoice->business_id === $request->attributes->get('membership')->business_id, 404);

        return view('business.invoices.show', ['invoice' => $invoice->load('shipments.service', 'business')]);
    }

    public function pay(Request $request, Invoice $invoice, PaymentService $payments)
    {
        abort_unless($invoice->business_id === $request->attributes->get('membership')->business_id, 404);
        $payment = $payments->startForInvoice($invoice, $request->user()->email, $request->user());

        return redirect()->away($payment->checkout_url);
    }
}
