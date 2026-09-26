@extends('layouts.portal', ['portal' => 'account'])
@section('title', 'Receipt '.$payment->reference)
@section('content')
<div class="mx-auto max-w-2xl">
    <div class="mb-4 flex justify-between print:hidden">
        <a href="{{ route('account.payments.index') }}" class="text-sm">← Back</a>
        <button onclick="window.print()" class="btn btn-secondary btn-sm" type="button"><x-icon name="download" class="size-4" />Print / save as PDF</button>
    </div>
    <article class="card card-body">
        <header class="flex items-start justify-between border-b border-ink-200 pb-4">
            <div><p class="text-xl font-bold">{{ \App\Support\Settings::get('business_name') }}</p><p class="text-sm text-ink-600">{{ \App\Support\Settings::get('office_address') }}</p></div>
            <div class="text-right"><p class="text-lg font-semibold">Receipt</p><p class="font-mono text-sm">{{ $payment->reference }}</p></div>
        </header>
        @if($payment->status !== 'successful' && $payment->status !== 'refunded')
            <x-alert type="warning" class="mt-4">This payment is {{ $payment->status }}. A receipt is only valid for verified payments.</x-alert>
        @endif
        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
            <div><dt class="text-ink-500">Paid by</dt><dd>{{ $payment->user?->name }} ({{ $payment->payer_email }})</dd></div>
            <div><dt class="text-ink-500">Date paid</dt><dd>{{ $payment->paid_at?->timezone(\App\Support\Settings::get('timezone'))->format('j M Y, g:i a') }}</dd></div>
            <div><dt class="text-ink-500">Method</dt><dd class="capitalize">{{ $payment->channel ?? $payment->provider }}</dd></div>
            <div><dt class="text-ink-500">Status</dt><dd class="capitalize">{{ $payment->status }}</dd></div>
        </dl>
        <table class="table mt-6">
            <thead><tr><th>Shipment</th><th>Service</th><th class="text-right">Amount</th></tr></thead>
            <tbody>@foreach($payment->shipments as $s)<tr><td class="font-mono">{{ $s->tracking_number }}</td><td>{{ $s->service->name }}</td><td class="text-right"><x-money :amount="$s->pivot->amount" :currency="$payment->currency" /></td></tr>@endforeach</tbody>
            <tfoot><tr><th colspan="2" class="text-right">Total paid</th><th class="text-right"><x-money :amount="$payment->amount" :currency="$payment->currency" /></th></tr></tfoot>
        </table>
        @foreach($payment->refunds as $r)
            <p class="mt-3 text-sm text-ink-600">Refund {{ $r->status }}: <x-money :amount="$r->amount" :currency="$payment->currency" /> on {{ $r->created_at->format('j M Y') }}</p>
        @endforeach
    </article>
</div>
@endsection
