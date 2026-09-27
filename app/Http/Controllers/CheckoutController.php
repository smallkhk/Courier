<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Shipment;
use App\Services\Payments\PaymentService;
use App\Support\ShipmentAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function show(Request $request, Shipment $shipment)
    {
        ShipmentAccess::authorize($request, $shipment);
        if (! $shipment->isPayable()) {
            return redirect()->route('book.confirmation', $shipment);
        }

        return view('booking.checkout', [
            'shipment' => $shipment->load('service'),
            'payments' => $shipment->payments()->latest('payments.id')->get(),
            'provider' => config('courier.payments.provider'),
        ]);
    }

    public function pay(Request $request, Shipment $shipment, PaymentService $payments)
    {
        ShipmentAccess::authorize($request, $shipment);
        if (! $shipment->isPayable()) {
            return redirect()->route('book.confirmation', $shipment)->with('status', 'This shipment is not awaiting payment.');
        }
        $email = $request->user()?->email ?? $shipment->sender_email;

        try {
            $payment = $payments->start(collect([$shipment]), $email, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'We could not start the payment right now. Please try again in a moment.');
        }

        return redirect()->away($payment->checkout_url);
    }

    /** Browser return from the provider. Never trusted on its own — we verify server-side. */
    public function callback(Request $request, PaymentService $payments)
    {
        $reference = (string) ($request->query('reference') ?? $request->query('trxref') ?? '');
        $payment = Payment::with('shipments')->where('reference', $reference)->first();
        if (! $payment) {
            abort(404);
        }

        try {
            $outcome = $payments->confirm($reference, 'callback');
        } catch (\Throwable $e) {
            report($e);
            $outcome = 'pending';
        }
        $payment->refresh();

        if ($payment->invoice_id) {
            return redirect()->route('business.invoices.show', Invoice::findOrFail($payment->invoice_id)->number)
                ->with($payment->status === 'successful' ? 'success' : 'warning', $payment->status === 'successful' ? 'Payment received. Thank you.' : 'Payment not completed ('.$payment->status.').');
        }

        $shipment = $payment->shipments->first();
        if ($payment->shipments->count() > 1) {
            return redirect()->route('business.shipments.index')->with($payment->status === 'successful' ? 'success' : 'warning',
                $payment->status === 'successful' ? 'Payment received for '.$payment->shipments->count().' shipments.' : 'Payment was not completed ('.$payment->status.').');
        }

        return match ($payment->status) {
            'successful' => redirect()->route('book.confirmation', $shipment)->with('success', 'Payment confirmed. Your shipment is booked.'),
            'failed', 'cancelled' => redirect()->route('checkout.show', $shipment)->with('error', 'Payment was not completed: '.($payment->failure_reason ?: $payment->status).'. You can try again.'),
            default => $request->boolean('cancelled')
                ? redirect()->route('checkout.show', $shipment)->with('status', 'Payment was cancelled. Nothing was charged — you can try again when you\'re ready.')
                : redirect()->route('checkout.show', $shipment)->with('warning', 'We are still waiting for confirmation from the payment provider. This page will update once it is confirmed — you will not be charged twice.'),
        };
    }

    /** Sandbox checkout page (development only; route is signed). */
    public function sandbox(string $reference)
    {
        abort_if(app()->environment('production'), 404);
        $payment = Payment::where('reference', $reference)->where('provider', 'sandbox')->firstOrFail();

        return view('booking.sandbox', ['payment' => $payment]);
    }

    public function sandboxComplete(Request $request, string $reference)
    {
        abort_if(app()->environment('production'), 404);
        $request->validate(['outcome' => 'required|in:success,failed,abandoned']);
        $payment = Payment::where('reference', $reference)->where('provider', 'sandbox')->where('status', 'pending')->firstOrFail();
        $payment->forceFill(['metadata' => array_merge($payment->metadata ?? [], ['sandbox_outcome' => $request->input('outcome')])])->save();

        return redirect()->route('payments.callback', ['reference' => $reference]);
    }
}
