@extends('layouts.portal', ['portal' => 'business'])
@section('title', 'Invoice '.$invoice->number)
@section('content')
<div class="mx-auto max-w-3xl">
    <div class="mb-4 flex flex-wrap justify-between gap-2 print:hidden">
        <a href="{{ route('business.invoices.index') }}" class="text-sm">← Invoices</a>
        <div class="flex gap-2">
            <button onclick="window.print()" class="btn btn-secondary btn-sm" type="button"><x-icon name="download" class="size-4" />Print / PDF</button>
            @if($invoice->status === 'issued')<form method="post" action="{{ route('business.invoices.pay', $invoice) }}">@csrf<button class="btn btn-primary btn-sm" type="submit">Pay online</button></form>@endif
        </div>
    </div>
    <article class="card card-body">
        <header class="flex flex-wrap justify-between gap-4 border-b border-ink-200 pb-4">
            <div><p class="text-xl font-bold">{{ \App\Support\Settings::get('business_name') }}</p><p class="text-sm text-ink-600">{{ \App\Support\Settings::get('office_address') }}</p></div>
            <div class="text-right"><p class="text-lg font-semibold">Invoice {{ $invoice->number }}</p><p class="text-sm">Issued {{ $invoice->issued_at?->format('j M Y') }} · Due {{ $invoice->due_at?->format('j M Y') }}</p><p class="mt-1"><x-pill :tone="$invoice->status === 'paid' ? 'success' : 'warning'">{{ ucfirst($invoice->status) }}</x-pill></p></div>
        </header>
        <div class="mt-4 text-sm"><p class="text-ink-500">Bill to</p><p class="font-medium">{{ $invoice->business->name }}</p><p>{{ $invoice->business->billing_address }}</p><p>{{ $invoice->business->billing_email ?? $invoice->business->email }}</p></div>
        <p class="mt-4 text-sm">Period: {{ $invoice->period_start->format('j M Y') }} – {{ $invoice->period_end->format('j M Y') }}</p>
        <div class="table-wrap mt-4"><table class="table">
            <thead><tr><th>Shipment</th><th>Date</th><th>Service</th><th>Route</th><th class="text-right">Amount</th></tr></thead>
            <tbody>@foreach($invoice->shipments as $s)<tr><td class="font-mono text-xs">{{ $s->tracking_number }}</td><td>{{ $s->created_at->format('j M') }}</td><td>{{ $s->service->name }}</td><td>{{ $s->pickup_city }} → {{ $s->delivery_city }}</td><td class="text-right"><x-money :amount="$s->total" :currency="$s->currency" /></td></tr>@endforeach</tbody>
            <tfoot>
                <tr><td colspan="4" class="text-right">Subtotal</td><td class="text-right"><x-money :amount="$invoice->subtotal" :currency="$invoice->currency" /></td></tr>
                <tr><td colspan="4" class="text-right">Tax</td><td class="text-right"><x-money :amount="$invoice->tax" :currency="$invoice->currency" /></td></tr>
                <tr><th colspan="4" class="text-right">Total</th><th class="text-right"><x-money :amount="$invoice->total" :currency="$invoice->currency" /></th></tr>
            </tfoot>
        </table></div>
    </article>
</div>
@endsection
