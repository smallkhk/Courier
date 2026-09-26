@extends('layouts.portal', ['portal' => 'ops'])
@section('title', 'Operations')
@section('content')
<x-page-header title="Operations" :subtitle="now()->timezone(\App\Support\Settings::get('timezone'))->format('l j F Y')">
    <a href="{{ route('ops.dispatch') }}" class="btn btn-primary"><x-icon name="route" class="size-4" />Dispatch board</a>
</x-page-header>
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
<div class="mt-6 grid gap-6 xl:grid-cols-3">
    <div class="card xl:col-span-2">
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
