@extends('layouts.portal', ['portal' => 'ops'])
@section('title', 'Dispatch')
@section('content')
<x-page-header title="Dispatch board" subtitle="Shipments without an active rider, oldest first." />
<div class="grid gap-6 xl:grid-cols-[1fr_360px]">
    <div class="card">
        <form method="get" class="card-header"><div class="flex items-end gap-2"><x-field name="zone" label="Destination zone" type="select" :options="$zones" :value="request('zone')" placeholder="All" /><button class="btn btn-secondary" type="submit">Filter</button></div></form>
        @if($unassigned->isEmpty())<x-empty icon="check-circle" title="No unassigned shipments" />@else
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Shipment</th><th>Route</th><th>Status</th><th>Waiting</th><th><span class="sr-only">Assign</span></th></tr></thead>
            <tbody>@foreach($unassigned as $s)
                @php $leg = in_array($s->status->value, ['booked','pickup_scheduled']) ? 'pickup' : 'delivery'; @endphp
                <tr>
                    <td><a href="{{ route('ops.shipments.show', $s) }}" class="font-mono font-semibold">{{ $s->tracking_number }}</a><div class="text-xs text-ink-500">{{ $s->service->name }} · {{ $s->parcel_count }} pcs</div></td>
                    <td>{{ $s->pickup_city }} → {{ $s->delivery_city }}<div class="text-xs text-ink-500">{{ $leg === 'pickup' ? $s->originZone->name : $s->destinationZone->name }}</div></td>
                    <td><x-status-badge :status="$s->status" /></td>
                    <td class="whitespace-nowrap">{{ $s->status_changed_at?->diffForHumans(null, true) }}</td>
                    <td>
                        <form method="post" action="{{ route('ops.shipments.assign', $s) }}" class="flex gap-2">
                            @csrf<input type="hidden" name="leg" value="{{ $leg }}">
                            <label class="sr-only" for="r{{ $s->id }}">Rider for {{ $s->tracking_number }}</label>
                            <select id="r{{ $s->id }}" name="rider_id" class="input min-h-9 py-1 text-sm" required>
                                <option value="">{{ ucfirst($leg) }} rider…</option>
                                @foreach($riders as $r)@if($r->open->count() < $r->max_active_assignments)<option value="{{ $r->user_id }}">{{ $r->user->name }} ({{ $r->open->count() }}{{ $r->on_duty ? '' : ', off' }})</option>@endif @endforeach
                            </select>
                            <button class="btn btn-primary btn-sm" type="submit">Assign</button>
                        </form>
                    </td>
                </tr>
            @endforeach</tbody>
        </table></div><div class="border-t border-ink-200 p-4">{{ $unassigned->links() }}</div>@endif
    </div>
    <aside class="space-y-3">
        <h2 class="text-base">Rider workload</h2>
        @foreach($riders as $r)
            <div class="card p-4 text-sm">
                <div class="flex justify-between"><a href="{{ route('ops.riders.show', $r->user) }}" class="font-semibold">{{ $r->user->name }}</a>
                    <x-pill :tone="$r->on_duty ? 'success' : 'neutral'">{{ $r->on_duty ? 'On duty' : 'Off duty' }}</x-pill></div>
                <p class="text-xs text-ink-500">{{ $r->zone?->name ?? 'No home zone' }} · {{ $r->vehicle_type }}</p>
                <div class="mt-2 h-2 rounded-full bg-ink-100" role="img" aria-label="{{ $r->open->count() }} of {{ $r->max_active_assignments }} jobs"><div class="h-2 rounded-full {{ $r->open->count() >= $r->max_active_assignments ? 'bg-danger-600' : 'bg-brand-500' }}" style="width: {{ min(100, round($r->open->count() / max(1,$r->max_active_assignments) * 100)) }}%"></div></div>
                <p class="mt-1 text-xs">{{ $r->open->count() }} / {{ $r->max_active_assignments }} jobs</p>
                @if($r->open->isNotEmpty())<p class="mt-1 text-xs text-ink-600">{{ $r->open->map(fn($a) => $a->shipment->tracking_number)->join(', ') }}</p>@endif
            </div>
        @endforeach
    </aside>
</div>
@endsection
