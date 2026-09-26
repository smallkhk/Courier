@extends('layouts.portal', ['portal' => 'business'])
@section('title', 'Invoices & payments')
@section('content')
<x-page-header title="Invoices & payments" />
<div class="card mb-6"><div class="card-header"><h2 class="text-base">Invoices</h2></div>
    @if($invoices->isEmpty())<x-empty icon="receipt" title="No invoices">Invoices are issued for accounts on invoice terms.</x-empty>@else
    <div class="table-wrap"><table class="table"><thead><tr><th>Number</th><th>Period</th><th>Status</th><th>Due</th><th class="text-right">Total</th></tr></thead><tbody>
    @foreach($invoices as $i)<tr>
        <td><a href="{{ route('business.invoices.show', $i) }}" class="font-mono">{{ $i->number }}</a></td>
        <td>{{ $i->period_start->format('j M') }} – {{ $i->period_end->format('j M Y') }}</td>
        <td><x-pill :tone="['paid' => 'success', 'issued' => ($i->due_at?->isPast() ? 'danger' : 'warning'), 'void' => 'neutral', 'draft' => 'neutral'][$i->status]">{{ $i->status === 'issued' && $i->due_at?->isPast() ? 'Overdue' : ucfirst($i->status) }}</x-pill></td>
        <td>{{ $i->due_at?->format('j M Y') }}</td><td class="text-right"><x-money :amount="$i->total" :currency="$i->currency" /></td></tr>@endforeach
    </tbody></table></div><div class="border-t border-ink-200 p-4">{{ $invoices->links() }}</div>@endif
</div>
<div class="card"><div class="card-header"><h2 class="text-base">Payment history</h2></div>
    @if($payments->isEmpty())<x-empty icon="credit-card" title="No payments yet" />@else
    <div class="table-wrap"><table class="table"><thead><tr><th>Reference</th><th>For</th><th>Status</th><th>Date</th><th class="text-right">Amount</th></tr></thead><tbody>
    @foreach($payments as $p)<tr>
        <td class="font-mono text-xs">{{ $p->reference }}</td>
        <td class="text-xs">{{ $p->invoice_id ? 'Invoice' : \Illuminate\Support\Str::limit($p->shipments->pluck('tracking_number')->join(', '), 60) }}</td>
        <td><x-pill :tone="['successful' => 'success', 'failed' => 'danger', 'pending' => 'warning', 'cancelled' => 'neutral', 'refunded' => 'info'][$p->status]">{{ ucfirst($p->status) }}</x-pill></td>
        <td>{{ ($p->paid_at ?? $p->created_at)->format('j M Y') }}</td><td class="text-right"><x-money :amount="$p->amount" :currency="$p->currency" /></td></tr>@endforeach
    </tbody></table></div><div class="border-t border-ink-200 p-4">{{ $payments->links() }}</div>@endif
</div>
@endsection
