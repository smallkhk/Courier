@extends('layouts.public')
@section('title', 'Branches & pickup points')
@section('hero')
<x-photo-hero image="warehouse-aisle" eyebrow="Branches" icon="warehouse" title="Branches &amp; pickup points" subtitle="Drop off, collect, or get help in person." />
@endsection
@section('content')
<form method="get" class="mb-6 flex flex-wrap items-end gap-3">
    <div class="w-full sm:w-64"><x-field name="country" label="Filter by country" type="select" :options="$countries" placeholder="All countries" :value="request('country')" /></div>
    <button class="btn btn-secondary" type="submit">Apply</button>
</form>
@if($branches->isEmpty())
    <div class="card"><x-empty icon="warehouse" title="No branches listed">Branch locations will appear here once configured.</x-empty></div>
@else
<div class="grid gap-6 lg:grid-cols-[1fr_1.1fr]">
    {{-- Accessible list view (primary); the map is an enhancement. --}}
    <ul class="space-y-4" aria-label="Branch list">
        @foreach($branches as $b)
            <li class="card card-body hover-lift" data-reveal>
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <h2 class="flex items-center gap-3 text-lg"><x-illus name="pin" size="size-10" />{{ $b->name }}</h2>
                    @if($b->is_pickup_point)<x-pill tone="info" icon="package">Pickup point</x-pill>@endif
                </div>
                <p class="mt-1 text-ink-700">{{ \App\Support\Countries::flag($b->country_code) }} {{ $b->fullAddress() }}</p>
                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                    @if($b->phone)<a href="tel:{{ $b->phone }}" class="inline-flex items-center gap-1"><x-icon name="phone" class="size-4" />{{ $b->phone }}</a>@endif
                    @if($b->email)<a href="mailto:{{ $b->email }}" class="inline-flex items-center gap-1"><x-icon name="mail" class="size-4" />{{ $b->email }}</a>@endif
                    <a href="{{ $b->directionsUrl() }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1"><x-icon name="navigation" class="size-4" />Directions<span class="sr-only"> to {{ $b->name }} (opens in new tab)</span></a>
                </div>
                @if($b->opening_hours)
                    <details class="mt-3 text-sm">
                        <summary class="cursor-pointer font-medium text-ink-700">Opening hours</summary>
                        <dl class="mt-2 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1">
                            @foreach(\App\Models\Branch::DAYS as $k => $day)
                                <dt class="text-ink-600">{{ $day }}</dt><dd>{{ $b->opening_hours[$k] ?? 'Closed' }}</dd>
                            @endforeach
                        </dl>
                    </details>
                @endif
                @foreach($b->upcomingClosures() as $c)
                    <p class="mt-2 text-sm text-warning-800"><x-icon name="calendar" class="inline size-4" /> Closed {{ \Illuminate\Support\Carbon::parse($c['date'])->format('D j M Y') }}@if(!empty($c['note'])) — {{ $c['note'] }}@endif</p>
                @endforeach
            </li>
        @endforeach
    </ul>
    <div class="lg:sticky lg:top-24 lg:self-start" x-data="branchMap(@js($mapPoints), @js(['tileUrl' => config('courier.maps.tile_url'), 'attribution' => config('courier.maps.attribution')]))">
        <div x-ref="map" class="h-80 overflow-hidden rounded-[var(--radius-md)] border border-ink-200 bg-ink-100 lg:h-[560px]" role="img" aria-label="Map of branch locations. The same information is available in the list."></div>
        <p class="mt-2 text-xs text-ink-500">Map data © OpenStreetMap contributors. Locations are approximate; use the directions link for navigation.</p>
    </div>
</div>
@endif
@endsection
