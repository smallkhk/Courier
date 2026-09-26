<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Invoice;
use App\Models\Shipment;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Consolidated invoices for businesses on invoice (credit) terms.
 */
class InvoiceService
{
    public function generate(Business $business, Carbon $from, Carbon $to): Invoice
    {
        return DB::transaction(function () use ($business, $from, $to) {
            $shipments = Shipment::where('business_id', $business->id)
                ->where('payment_method', 'invoice')
                ->whereNull('invoice_id')
                ->whereNotIn('status', ['cancelled', 'draft'])
                ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->lockForUpdate()->get();

            if ($shipments->isEmpty()) {
                throw ValidationException::withMessages(['period' => 'No uninvoiced shipments in this period.']);
            }
            if ($shipments->pluck('currency')->unique()->count() > 1) {
                throw ValidationException::withMessages(['period' => 'Shipments in multiple currencies — invoice each currency separately.']);
            }

            $sub = $shipments->sum(fn ($s) => Money::toMinor($s->subtotal));
            $tax = $shipments->sum(fn ($s) => Money::toMinor($s->tax));
            $invoice = Invoice::create([
                'number' => $this->nextNumber(),
                'business_id' => $business->id,
                'period_start' => $from->toDateString(),
                'period_end' => $to->toDateString(),
                'subtotal' => Money::fromMinor($sub),
                'tax' => Money::fromMinor($tax),
                'total' => Money::fromMinor($sub + $tax),
                'currency' => $shipments->first()->currency,
                'status' => 'issued',
                'issued_at' => now(),
                'due_at' => now()->addDays($business->invoice_due_days)->toDateString(),
            ]);
            Shipment::whereIn('id', $shipments->pluck('id'))->update(['invoice_id' => $invoice->id]);
            Audit::log('invoice.issued', $invoice, ['business_id' => $business->id, 'shipments' => $shipments->count(), 'total' => $invoice->total]);

            return $invoice;
        });
    }

    private function nextNumber(): string
    {
        $prefix = 'INV-'.now()->format('Ym').'-';
        $last = Invoice::where('number', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('number')->value('number');
        $n = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
