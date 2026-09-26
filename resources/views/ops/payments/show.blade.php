@extends('layouts.portal', ['portal' => 'ops'])
@section('title', $p->reference)
@section('content')
<x-page-header :title="$p->reference" :back="route('ops.payments.index')">
    <x-pill :tone="['successful'=>'success','failed'=>'danger','pending'=>'warning','cancelled'=>'neutral','refunded'=>'info'][$p->status]" class="self-center">{{ ucfirst($p->status) }}</x-pill>
</x-page-header>
<div class="grid gap-6 lg:grid-cols-[1fr_360px]">
    <div class="card card-body">
        <dl class="grid gap-4 text-sm sm:grid-cols-2">
            <div><dt class="text-ink-500">Amount</dt><dd class="text-xl font-bold"><x-money :amount="$p->amount" :currency="$p->currency" /></dd></div>
            <div><dt class="text-ink-500">Provider</dt><dd>{{ $p->provider }} {{ $p->channel ? '· '.$p->channel : '' }}</dd></div>
            <div><dt class="text-ink-500">Provider transaction</dt><dd class="font-mono text-xs">{{ $p->provider_transaction_id ?? '—' }}</dd></div>
            <div><dt class="text-ink-500">Payer</dt><dd>{{ $p->business?->name ?? $p->user?->name }} · {{ $p->payer_email }}</dd></div>
            <div><dt class="text-ink-500">Paid at</dt><dd>{{ $p->paid_at?->format('j M Y H:i') ?? '—' }}</dd></div>
            <div><dt class="text-ink-500">Verified at</dt><dd>{{ $p->verified_at?->format('j M Y H:i') ?? '—' }} {{ $p->metadata['verified_via'] ?? '' }}</dd></div>
            @if($p->failure_reason)<div class="sm:col-span-2"><dt class="text-ink-500">Failure reason</dt><dd class="text-danger-700">{{ $p->failure_reason }}</dd></div>@endif
            <div class="sm:col-span-2"><dt class="text-ink-500">Shipments</dt><dd>@forelse($p->shipments as $s)<a href="{{ route('ops.shipments.show', $s) }}" class="mr-2 font-mono">{{ $s->tracking_number }}</a>@empty Invoice payment @endforelse</dd></div>
        </dl>
        <h2 class="mt-6 text-base">Refunds</h2>
        <ul class="mt-2 text-sm">@forelse($p->refunds as $r)<li><x-money :amount="$r->amount" :currency="$p->currency" /> · {{ $r->status }} · {{ $r->reason }} · by {{ $r->requester->name }} {{ $r->created_at->format('j M Y') }} {{ $r->failure_reason }}</li>@empty<li class="text-ink-500">None</li>@endforelse</ul>
    </div>
    <aside class="space-y-4">
        @if($p->status === 'pending')
            <form method="post" action="{{ route('ops.payments.verify', $p) }}" class="card card-body">@csrf
                <p class="text-sm text-ink-600">Customer says they paid? Ask the provider for the current state.</p>
                <button class="btn btn-secondary mt-3 w-full" type="submit">Re-verify with provider</button></form>
        @endif
        @if(auth()->user()->isRole('admin') && $p->status === 'successful')
            <form method="post" action="{{ route('ops.payments.refund', $p) }}" class="card card-body space-y-3" x-data @submit="if(!confirm('Issue this refund? This sends money back to the payer.')) $event.preventDefault()">
                @csrf
                <h2 class="text-base">Refund (admin)</h2>
                <x-field name="amount" label="Amount" type="number" step="0.01" min="0.01" :value="$p->amount" required />
                <x-field name="reason" label="Reason" required />
                <button class="btn btn-danger w-full" type="submit">Issue refund</button>
                <p class="text-xs text-ink-500">Refunds are processed through {{ $p->provider }} and recorded in the audit log.</p>
            </form>
        @endif
    </aside>
</div>
@endsection
