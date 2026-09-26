@extends('layouts.portal', ['portal' => 'account'])
@section('title', 'Payments & receipts')
@section('content')
<x-page-header title="Payments & receipts" />
<div class="card">
    @if($payments->isEmpty())
        <x-empty icon="receipt" title="No payments yet" />
    @else
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Reference</th><th>Shipments</th><th>Status</th><th class="text-right">Amount</th><th>Date</th><th><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
        @foreach($payments as $p)
            <tr>
                <td class="font-mono text-xs">{{ $p->reference }}</td>
                <td>{{ $p->shipments->pluck('tracking_number')->join(', ') }}</td>
                <td><x-pill :tone="['successful' => 'success', 'failed' => 'danger', 'pending' => 'warning', 'cancelled' => 'neutral', 'refunded' => 'info'][$p->status]">{{ ucfirst($p->status) }}</x-pill></td>
                <td class="text-right"><x-money :amount="$p->amount" :currency="$p->currency" /></td>
                <td>{{ ($p->paid_at ?? $p->created_at)->format('j M Y') }}</td>
                <td>@if(in_array($p->status, ['successful','refunded']))<a href="{{ route('account.payments.show', $p) }}">Receipt</a>@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    <div class="border-t border-ink-200 p-4">{{ $payments->links() }}</div>
    @endif
</div>
@endsection
