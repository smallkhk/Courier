@extends('layouts.public')
@section('title', 'Tracking '.$shipment->tracking_number)
@section('content')
@php
    $tz = \App\Support\Settings::get('timezone');
    $latest = $shipment->events->sortByDesc(fn($e) => [$e->occurred_at, $e->id])->first();
    $stages = [
        ['Booked', ['booked', 'pickup_scheduled', 'rider_assigned']],
        ['Picked up', ['picked_up', 'at_origin_facility']],
        ['In transit', ['in_transit', 'at_destination_facility']],
        ['Out for delivery', ['out_for_delivery', 'delivery_attempted', 'delivery_exception']],
        ['Delivered', ['delivered']],
    ];
    $order = collect($stages)->search(fn($s) => in_array($shipment->status->value, $s[1]));
@endphp
<div class="mx-auto max-w-4xl">
    <div class="card overflow-hidden" data-reveal>
        <div class="photo-hero px-5 py-6 sm:px-8"><img src="{{ asset('images/truck-night-sm.webp') }}" alt="">
            <p class="text-sm text-brand-200">Tracking number</p>
            <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
                <p class="font-mono text-2xl font-bold tracking-wider">{{ $shipment->tracking_number }}</p>
                <x-status-badge :status="$shipment->status" class="text-sm" />
            </div>
            <p class="mt-3 text-brand-100">{{ $latest?->public_description ?? $shipment->status->publicDescription() }}</p>
            <p class="mt-1 text-sm text-brand-200">Last update: <time datetime="{{ $shipment->status_changed_at?->toIso8601String() }}">{{ $shipment->status_changed_at?->timezone($tz)->format('D j M Y, g:i a') }}</time></p>
        </div>

        @if($order !== false)
        <div class="border-b border-ink-200 px-5 py-6 sm:px-8">
            <ol class="grid grid-cols-5 gap-1 text-center text-xs" aria-label="Progress">
                @foreach($stages as $i => [$label])
                    @php $done = $i <= $order; @endphp
                    <li class="flex flex-col items-center gap-2">
                        <span class="h-1.5 w-full overflow-hidden rounded-full bg-ink-200"><span class="block h-full rounded-full bg-brand-600 {{ $done ? 'progress-fill' : 'scale-x-0' }}" style="animation-delay: {{ $i * 220 }}ms"></span></span>
                        <span class="{{ $done ? 'font-semibold text-ink-900' : 'text-ink-500' }}">{{ $label }}@if($i === $order)<span class="sr-only"> (current)</span>@endif</span>
                    </li>
                @endforeach
            </ol>
        </div>
        @endif

        <div class="grid gap-6 px-5 py-6 sm:px-8 md:grid-cols-3">
            <div><p class="text-xs font-medium tracking-wide text-ink-500 uppercase">From</p><p class="mt-1 font-medium">{{ \App\Support\Countries::flag($shipment->pickup_country) }} {{ $shipment->area('pickup') }}</p></div>
            <div><p class="text-xs font-medium tracking-wide text-ink-500 uppercase">To</p><p class="mt-1 font-medium">{{ \App\Support\Countries::flag($shipment->delivery_country) }} {{ $shipment->area('delivery') }}</p></div>
            <div>
                <p class="text-xs font-medium tracking-wide text-ink-500 uppercase">Estimated delivery</p>
                @if($shipment->status->value === 'delivered')
                    <p class="mt-1 font-medium">Delivered {{ $shipment->delivered_at?->timezone($tz)->format('D j M') }}</p>
                @elseif($shipment->estimated_delivery_to && ! $shipment->status->isTerminal())
                    <p class="mt-1 font-medium">{{ $shipment->estimated_delivery_from?->format('D j M') }}@if($shipment->estimated_delivery_to != $shipment->estimated_delivery_from) – {{ $shipment->estimated_delivery_to->format('D j M') }}@endif</p>
                    <p class="text-xs text-ink-500">Estimate based on the {{ $shipment->service->name }} service</p>
                @else
                    <p class="mt-1 text-ink-600">Not available</p>
                @endif
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_320px]">
        <section class="card card-body" aria-labelledby="history" data-reveal>
            <h2 id="history" class="mb-6 text-lg">Shipment history</h2>
            <x-timeline :events="$shipment->events" />
        </section>
        <aside class="space-y-4">
            @if(! $verified)
                <form method="post" action="{{ route('track.verify', $shipment->tracking_number) }}" class="card card-body space-y-3">
                    @csrf
                    <h2 class="flex items-center gap-2 text-base"><x-icon name="lock" class="size-4" />See more details</h2>
                    <p class="text-sm text-ink-600">Recipient? Enter the last 4 digits of the recipient's phone number.</p>
                    <x-field name="phone_last4" label="Last 4 digits" inputmode="numeric" maxlength="4" pattern="[0-9]{4}" autocomplete="off" required />
                    <button class="btn btn-secondary w-full" type="submit">Verify</button>
                </form>
            @else
                <div class="card card-body text-sm">
                    <h2 class="flex items-center gap-2 text-base"><x-icon name="shield" class="size-4 text-success-600" />Verified details</h2>
                    <dl class="mt-3 space-y-2">
                        <div><dt class="text-ink-500">Recipient</dt><dd>{{ $shipment->recipient_name }}</dd></div>
                        <div><dt class="text-ink-500">Delivery area</dt><dd>{{ $shipment->area('delivery') }}</dd></div>
                        <div><dt class="text-ink-500">Service</dt><dd>{{ $shipment->service->name }} · {{ $shipment->parcel_count }} parcel(s)</dd></div>
                    </dl>
                </div>
                @if($approx)
                    <div class="card card-body" x-data="approxMap(@js(['lat' => $approx['lat'], 'lng' => $approx['lng']]), @js(['tileUrl' => config('courier.maps.tile_url'), 'attribution' => config('courier.maps.attribution')]))">
                        <h2 class="text-base">Approximate driver area</h2>
                        <div x-ref="map" class="mt-3 h-48 rounded-lg border border-ink-200" role="img" aria-label="Approximate area of the delivery agent"></div>
                        <p class="mt-2 text-xs text-ink-600">
                            @if($approx['fresh'])<span class="badge badge-success">Recent</span>@else<span class="badge badge-neutral">Not live</span>@endif
                            Position from {{ $approx['at']->diffForHumans() }}, shown to within about 1 km. GPS accuracy varies by device.
                        </p>
                    </div>
                @endif
            @endif
            <div class="card card-body text-sm">
                <h2 class="text-base">Problem with this delivery?</h2>
                <p class="mt-1 text-ink-600">Report a delay, damage or a dispute and we'll look into it.</p>
                <a href="{{ route('support.contact', ['tracking' => $shipment->tracking_number]) }}" class="btn btn-secondary mt-3 w-full">Get help</a>
            </div>
        </aside>
    </div>
</div>
@endsection
