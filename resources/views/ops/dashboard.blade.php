@extends('layouts.portal', ['portal' => 'ops'])
@section('title', 'Operations')
@section('content')
<x-dash-hero image="highway" eyebrow="Control center" title="Operations" :live="true" from="Pickup" to="Delivered">
    <a href="{{ route('ops.dispatch') }}" class="btn btn-accent btn-lg btn-shine"><x-icon name="route" class="size-4" />Dispatch board</a>
    <a href="{{ route('ops.map') }}" class="btn btn-on-dark btn-lg"><x-icon name="map" class="size-4" />Rider map</a>
</x-dash-hero>
<div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
    <x-stat label="Booked today" :value="$ops['booked_today']" icon="calendar" :href="route('ops.shipments.index', ['from' => today()->toDateString()])" />
    <x-stat label="Awaiting pickup" :value="$ops['awaiting_pickup']" icon="package" />
    <x-stat label="In transit" :value="$ops['in_transit']" icon="truck" :href="route('ops.shipments.index', ['status' => 'in_transit'])" />
    <x-stat label="Out for delivery" :value="$ops['out_for_delivery']" icon="bike" :href="route('ops.shipments.index', ['status' => 'out_for_delivery'])" />
    <x-stat label="Delivered today" :value="$ops['delivered_today']" icon="check-circle" />
    <x-stat label="Failed today" :value="$ops['failed_today']" icon="alert" :href="route('ops.exceptions')" />
    <x-stat label="Unassigned" :value="$ops['unassigned']" icon="user" :href="route('ops.dispatch')" />
    <x-stat label="Exceptions open" :value="$ops['exceptions_open']" icon="alert" :href="route('ops.exceptions')" />
    <x-stat label="Pending payments" :value="$ops['pending_payments']" icon="credit-card" :href="route('ops.payments.index', ['status' => 'pending'])" />
    <x-stat label="Pending refunds" :value="$ops['pending_refunds']" icon="undo" />
    <x-stat label="Open tickets" :value="$ops['open_tickets']" icon="life-buoy" :href="route('ops.support.index')" />
</div>
@php
    $pipe = [
        ['Awaiting pickup', $ops['awaiting_pickup'], '#fbbf24'],
        ['In transit', $ops['in_transit'], '#38bdf8'],
        ['Out for delivery', $ops['out_for_delivery'], '#a78bfa'],
        ['Delivered today', $ops['delivered_today'], '#34d399'],
        ['Exceptions', $ops['exceptions_open'], '#fb7185'],
    ];
    $pipeTotal = max(1, array_sum(array_column($pipe, 1)));
@endphp
<div class="mt-6 grid gap-6 xl:grid-cols-3">
    <div class="card xl:col-span-2" data-reveal>
        <div class="card-header"><h2 class="text-base">Today's pipeline</h2><span class="text-xs text-ink-500">{{ $n = array_sum(array_column($pipe, 1)) }} {{ Str::plural('shipment', $n) }} on the board</span></div>
        <div class="card-body">
            <div class="flex h-4 overflow-hidden rounded-full bg-ink-100" role="img" aria-label="@foreach($pipe as [$l, $n]){{ $l }}: {{ $n }}. @endforeach">
                @foreach($pipe as $k => [$l, $n, $c])
                    @if($n > 0)<span class="progress-fill h-full" style="width: {{ $n / $pipeTotal * 100 }}%; background: {{ $c }}; animation-delay: {{ $k * 120 }}ms"></span>@endif
                @endforeach
            </div>
            <ul class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-5">
                @foreach($pipe as [$l, $n, $c])
                    <li class="rounded-xl border border-ink-100 bg-white p-3">
                        <p class="flex items-center gap-1.5 text-xs font-medium text-ink-500"><span class="size-2.5 rounded-full" style="background: {{ $c }}"></span>{{ $l }}</p>
                        <p class="mt-1 text-xl font-bold tabular-nums">{{ $n }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
    <div class="card" data-reveal>
        <div class="card-header"><h2 class="text-base">Live activity</h2><span class="inline-flex items-center gap-1.5 text-xs font-semibold text-success-700"><span class="size-2 animate-pulse rounded-full bg-success-600"></span>Latest</span></div>
        @if($activity->isEmpty())<x-empty icon="history" title="No activity yet" />@else
        <ol class="divide-y divide-ink-100">
            @foreach($activity as $e)
                <li class="feed-item flex items-start gap-3 px-4 py-2.5 sm:px-6" style="--i: {{ $loop->index }}">
                    <span class="badge badge-{{ $e->status->tone() }} mt-0.5 size-8 shrink-0 justify-center rounded-lg p-0"><x-icon :name="$e->status->icon()" class="size-4" /></span>
                    <div class="min-w-0 text-sm">
                        <p class="truncate font-medium text-ink-900">{{ $e->status->label() }} @if($e->shipment)<a href="{{ route('ops.shipments.show', $e->shipment) }}" class="font-mono text-xs">{{ $e->shipment->tracking_number }}</a>@endif</p>
                        <p class="text-xs text-ink-500">{{ $e->occurred_at->diffForHumans() }}@if($e->location) · {{ $e->location }}@endif</p>
                    </div>
                </li>
            @endforeach
        </ol>@endif
    </div>
</div>
<div class="mt-6 grid gap-6 xl:grid-cols-3">
    <div class="card xl:col-span-2" data-reveal>
        <div class="card-header"><h2 class="text-base">Needs a rider</h2><a href="{{ route('ops.dispatch') }}" class="text-sm">Dispatch board</a></div>
        @if($unassigned->isEmpty())<x-empty icon="check-circle" title="Everything is assigned" />@else
        <ul class="divide-y divide-ink-100">
            @foreach($unassigned as $s)
                <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 sm:px-6">
                    <div><a href="{{ route('ops.shipments.show', $s) }}" class="font-mono font-semibold">{{ $s->tracking_number }}</a>
                        <p class="text-xs text-ink-500">{{ $s->pickup_city }} → {{ $s->delivery_city }} · {{ $s->service->name }} · waiting {{ $s->status_changed_at?->diffForHumans(null, true) }}</p></div>
                    <x-status-badge :status="$s->status" />
                </li>
            @endforeach
        </ul>@endif
    </div>
    <div class="card">
        <div class="card-header"><h2 class="text-base">Riders</h2><a href="{{ route('ops.map') }}" class="text-sm">Map</a></div>
        <ul class="divide-y divide-ink-100">
            @forelse($riders as $r)
                @php $f = $r->locationFreshness(); @endphp
                <li class="flex items-center justify-between gap-2 px-4 py-3 text-sm sm:px-6">
                    <span class="flex items-center gap-2"><span class="size-2.5 rounded-full {{ $r->on_duty ? 'bg-success-600' : 'bg-ink-300' }}" aria-hidden="true"></span>{{ $r->user->name }}<span class="sr-only">{{ $r->on_duty ? '(on duty)' : '(off duty)' }}</span></span>
                    <span class="text-xs text-ink-500">{{ $r->activeAssignmentCount() }} jobs · @if($f === 'live')<span class="text-success-700">live location</span>@elseif($f === 'stale')location {{ $r->last_location_at->diffForHumans() }}@else no location @endif</span>
                </li>
            @empty<li class="p-4 text-sm text-ink-500">No active riders.</li>@endforelse
        </ul>
    </div>
</div>
@if($attempts->isNotEmpty())
<div class="card mt-6">
    <div class="card-header"><h2 class="text-base">Failed attempts awaiting a decision</h2><a href="{{ route('ops.exceptions') }}" class="text-sm">Exceptions</a></div>
    <ul class="divide-y divide-ink-100">@foreach($attempts as $at)<li class="flex flex-wrap justify-between gap-2 px-4 py-3 text-sm sm:px-6"><a href="{{ route('ops.shipments.show', $at->shipment) }}" class="font-mono">{{ $at->shipment->tracking_number }}</a><span>{{ \App\Models\DeliveryAttempt::REASONS[$at->reason] }} · {{ $at->attempted_at->diffForHumans() }}</span></li>@endforeach</ul>
</div>
@endif
@endsection
