@extends('layouts.portal', ['portal' => 'ops'])
@section('title', 'Payments')
@section('content')
<x-page-header title="Payments" subtitle="Online payments are marked successful only after verification with the provider." />
<form method="get" class="mb-4 flex flex-wrap items-end gap-3">
    <x-field name="q" label="Reference" :value="request('q')" />
    <x-field name="status" label="Status" type="select" :options="['pending'=>'Pending','successful'=>'Successful','failed'=>'Failed','cancelled'=>'Cancelled','refunded'=>'Refunded']" :value="request('status')" placeholder="Any" />
    <button class="btn btn-secondary" type="submit">Filter</button>
</form>
<div class="card">@if($payments->isEmpty())<x-empty icon="credit-card" title="No payments" />@else
<div class="table-wrap"><table class="table">
    <thead><tr><th>Reference</th><th>Payer</th><th>For</th><th>Provider</th><th>Status</th><th class="text-right">Amount</th><th>Created</th></tr></thead>
    <tbody>@foreach($payments as $p)<tr>
        <td><a href="{{ route('ops.payments.show', $p) }}" class="font-mono text-xs">{{ $p->reference }}</a></td>
        <td>{{ $p->business?->name ?? $p->user?->name ?? $p->payer_email }}</td>
        <td class="text-xs">{{ $p->invoice_id ? 'Invoice' : \Illuminate\Support\Str::limit($p->shipments->pluck('tracking_number')->join(', '), 40) }}</td>
        <td>{{ $p->provider }}</td>
        <td><x-pill :tone="['successful'=>'success','failed'=>'danger','pending'=>'warning','cancelled'=>'neutral','refunded'=>'info'][$p->status]">{{ ucfirst($p->status) }}</x-pill></td>
        <td class="text-right"><x-money :amount="$p->amount" :currency="$p->currency" /></td>
        <td>{{ $p->created_at->format('j M H:i') }}</td>
    </tr>@endforeach</tbody>
</table></div><div class="border-t border-ink-200 p-4">{{ $payments->links() }}</div>@endif</div>
@endsection
