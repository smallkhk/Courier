@extends('layouts.portal', ['portal' => 'rider'])
@section('title', 'Delivery history')
@section('content')
<x-page-header title="Delivery history" />
<div class="card">
    @if($rows->isEmpty())<x-empty icon="history" title="No completed jobs yet" />@else
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Shipment</th><th>Leg</th><th>Result</th><th>Assigned</th><th>Closed</th></tr></thead>
        <tbody>@foreach($rows as $a)<tr>
            <td><a href="{{ route('rider.jobs.show', $a) }}" class="font-mono">{{ $a->shipment->tracking_number }}</a><div class="text-xs text-ink-500">{{ $a->shipment->delivery_city }}</div></td>
            <td>{{ ucfirst($a->leg) }}</td>
            <td><x-pill :tone="$a->status === 'completed' ? 'success' : 'neutral'">{{ ucfirst($a->status) }}</x-pill></td>
            <td>{{ $a->assigned_at->format('j M, g:i a') }}</td><td>{{ $a->ended_at?->format('j M, g:i a') ?? '—' }}</td>
        </tr>@endforeach</tbody>
    </table></div><div class="border-t border-ink-200 p-4">{{ $rows->links() }}</div>@endif
</div>
@endsection
