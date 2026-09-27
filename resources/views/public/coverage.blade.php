@extends('layouts.public')
@section('title', 'Coverage areas')
@section('hero')
<x-photo-hero image="highway" eyebrow="Coverage" icon="globe" title="Where we pick up and deliver" subtitle="Local, nationwide and international routes. Your address is checked automatically when you book.">
    <a href="{{ route('branches') }}" class="btn btn-on-dark btn-lg"><x-icon name="map-pin" class="size-4" />Branches &amp; pickup points</a>
</x-photo-hero>
@endsection
@section('content')
@forelse($zones as $country => $list)
    <section class="mb-10" data-reveal>
        <h2 class="mb-4 flex items-center gap-2 text-xl">@php $cc = $list->first()->country_code; @endphp<span class="text-2xl" aria-hidden="true">{{ $cc ? \App\Support\Countries::flag($cc) : '🌍' }}</span>{{ $country }}</h2>
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach($list as $z)
                <div class="card card-body hover-lift">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="flex items-center gap-3 font-semibold"><x-illus name="pin" :tone="$z->is_remote ? 'accent' : 'brand'" size="size-10" />{{ $z->name }}</h3>
                        @if($z->is_remote)<x-pill tone="warning" icon="alert">Remote area</x-pill>@endif
                    </div>
                    <ul class="mt-3 space-y-1 text-sm">
                        <li class="flex items-center gap-2">@if($z->pickup_enabled)<x-icon name="check" class="size-4 text-success-600" />Pickup available @else<x-icon name="x" class="size-4 text-danger-600" />No pickup — drop off at a branch @endif</li>
                        <li class="flex items-center gap-2">@if($z->delivery_enabled)<x-icon name="check" class="size-4 text-success-600" />Delivery available @else<x-icon name="x" class="size-4 text-danger-600" />Delivery unavailable @endif</li>
                    </ul>
                    <p class="mt-3 text-sm text-ink-600"><strong>Covers:</strong> {{ $z->coverageSummary() }}</p>
                    @if($z->services->isNotEmpty())<p class="mt-2 text-xs text-ink-500">Services: {{ $z->services->pluck('name')->join(', ') }}</p>@endif
                </div>
            @endforeach
        </div>
    </section>
@empty
    <div class="card"><x-empty icon="globe" title="No coverage areas published yet">Please contact us to ask about a delivery.</x-empty></div>
@endforelse
@endsection
