@extends('layouts.portal', ['portal' => 'ops'])
@section('title', 'Cash on delivery')
@section('content')
<x-page-header title="Cash on delivery" subtitle="Collected by riders → remitted to a branch → reconciled by finance." />
<form method="get" class="mb-4 flex items-end gap-3"><x-field name="status" label="Status" type="select" :options="['pending'=>'Pending','collected'=>'Collected','remitted'=>'Remitted','reconciled'=>'Reconciled','discrepancy'=>'Discrepancy']" :value="request('status')" placeholder="Any" /><button class="btn btn-secondary" type="submit">Filter</button></form>
<div class="card">@if($rows->isEmpty())<x-empty icon="wallet" title="No cash-on-delivery shipments">Cash on delivery is {{ \App\Support\Settings::get('cod_enabled') ? 'enabled' : 'disabled' }} in settings.</x-empty>@else
<div class="table-wrap"><table class="table">
    <thead><tr><th>Shipment</th><th>Rider</th><th class="text-right">Due</th><th class="text-right">Collected</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>@foreach($rows as $c)<tr>
        <td><a href="{{ route('ops.shipments.show', $c->shipment) }}" class="font-mono">{{ $c->shipment->tracking_number }}</a></td>
        <td>{{ $c->rider?->name ?? '—' }}</td>
        <td class="text-right"><x-money :amount="$c->amount_due" :currency="$c->currency" /></td>
        <td class="text-right">@if($c->amount_collected !== null)<x-money :amount="$c->amount_collected" :currency="$c->currency" />@else—@endif</td>
        <td><x-pill :tone="['reconciled'=>'success','discrepancy'=>'danger','pending'=>'neutral'][$c->status] ?? 'info'">{{ ucfirst($c->status) }}</x-pill>@if($c->note)<div class="text-xs text-ink-500">{{ $c->note }}</div>@endif</td>
        <td>@if(in_array($c->status, ['collected','remitted','discrepancy']))
            <form method="post" action="{{ route('ops.cod.update', $c) }}" class="flex gap-2">@csrf @method('PUT')
                <label class="sr-only" for="a{{ $c->id }}">Action</label>
                <select id="a{{ $c->id }}" name="action" class="input min-h-9 py-1 text-sm" required><option value="">Action…</option>
                    @if(in_array($c->status, ['collected','discrepancy']))<option value="remitted">Mark remitted</option>@endif
                    @if(in_array($c->status, ['remitted','discrepancy']))<option value="reconciled">Mark reconciled</option>@endif
                    @if($c->status !== 'discrepancy')<option value="discrepancy">Flag discrepancy</option>@endif
                </select>
                <input name="note" class="input min-h-9 py-1 text-sm" placeholder="Note" aria-label="Note">
                <button class="btn btn-secondary btn-sm" type="submit">Save</button></form>@endif</td>
    </tr>@endforeach</tbody>
</table></div><div class="border-t border-ink-200 p-4">{{ $rows->links() }}</div>@endif</div>
@endsection
