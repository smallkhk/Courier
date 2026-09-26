@extends('layouts.public')
@section('bare', true)
@section('content')
@php $brand = \App\Support\Settings::get('business_name'); @endphp
<section class="relative overflow-hidden bg-brand-950 text-white">
    <div class="pointer-events-none absolute inset-0 opacity-40" aria-hidden="true"
         style="background: radial-gradient(60rem 30rem at 85% -10%, rgb(52 97 238 / .55), transparent), radial-gradient(40rem 20rem at 0% 110%, rgb(245 180 30 / .25), transparent);"></div>
    <div class="relative mx-auto grid max-w-7xl items-center gap-10 px-4 py-14 sm:px-6 lg:grid-cols-[1.1fr_1fr] lg:py-24">
        <div>
            <x-flash />
            <p class="mt-2 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-medium text-brand-100">
                <x-icon name="shield" class="size-3.5" /> Verified payments · Proof of delivery on every drop-off
            </p>
            <h1 class="mt-5 text-[length:var(--text-fluid-h1)] leading-[1.08] font-bold tracking-tight text-white">
                Send parcels across town and across the country — <span class="text-accent-300">and always know where they are.</span>
            </h1>
            <p class="mt-5 max-w-xl text-lg text-brand-100">
                Book a pickup in minutes, pay securely online, and follow every step of the journey — from pickup to signature.
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('book.start') }}" class="btn btn-accent btn-lg">Send a parcel <x-icon name="arrow-right" class="size-4" /></a>
                <a href="{{ route('pricing') }}" class="btn btn-on-dark btn-lg">Get a price</a>
            </div>
        </div>

        <div class="card p-6 text-ink-800 shadow-xl sm:p-8">
            <h2 class="flex items-center gap-2 text-xl"><x-icon name="search" class="size-5 text-brand-600" />Track a parcel</h2>
            <p class="mt-1 text-sm text-ink-600">Enter the tracking number from your receipt, email or SMS.</p>
            <form action="{{ route('track.lookup') }}" method="post" class="mt-5 space-y-3">
                @csrf
                <label for="hero-tracking" class="label">Tracking number</label>
                <input id="hero-tracking" name="tracking_number" required maxlength="40" autocomplete="off" spellcheck="false"
                       class="input font-mono text-lg tracking-wider uppercase placeholder:normal-case placeholder:tracking-normal" placeholder="e.g. CX7K2M9QH4TRP8">
                <button type="submit" class="btn btn-primary btn-lg w-full">Track shipment</button>
            </form>
            <div class="mt-6 grid grid-cols-3 gap-3 border-t border-ink-200 pt-5 text-center text-xs text-ink-600">
                <div><x-icon name="calendar" class="mx-auto mb-1 size-5 text-brand-600" />Scheduled pickups</div>
                <div><x-icon name="history" class="mx-auto mb-1 size-5 text-brand-600" />Full event history</div>
                <div><x-icon name="check-circle" class="mx-auto mb-1 size-5 text-brand-600" />Signed delivery</div>
            </div>
        </div>
    </div>
</section>

<section class="mx-auto max-w-7xl px-4 py-16 sm:px-6" aria-labelledby="how">
    <h2 id="how" class="text-center text-[length:var(--text-fluid-h2)]">How it works</h2>
    <ol class="mt-10 grid gap-6 md:grid-cols-3">
        @foreach([
            ['route', 'Tell us where', 'Enter pickup and delivery details. We check the route is covered before you pay anything.'],
            ['credit-card', 'Get a price, pay securely', 'See the full price breakdown upfront. Payment is confirmed directly with the payment provider.'],
            ['truck', 'Track to the door', 'Get a tracking number instantly and updates at every step, through to proof of delivery.'],
        ] as $i => [$icon, $title, $text])
            <li class="card card-body relative">
                <span class="absolute top-5 right-5 text-4xl font-bold text-ink-100" aria-hidden="true">{{ $i + 1 }}</span>
                <span class="grid size-11 place-items-center rounded-xl bg-brand-50 text-brand-600"><x-icon :name="$icon" /></span>
                <h3 class="mt-4 text-lg">{{ $title }}</h3>
                <p class="mt-2 text-ink-600">{{ $text }}</p>
            </li>
        @endforeach
    </ol>
</section>

@if($services->isNotEmpty())
<section class="border-y border-ink-200 bg-white" aria-labelledby="svc">
    <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 id="svc" class="text-[length:var(--text-fluid-h2)]">Delivery services</h2>
                <p class="mt-2 text-ink-600">Choose the speed that fits. Availability depends on the route.</p>
            </div>
            <a href="{{ route('services') }}" class="btn btn-secondary">All services <x-icon name="arrow-right" class="size-4" /></a>
        </div>
        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($services as $s)
                <div class="rounded-[var(--radius-md)] border border-ink-200 p-5 transition hover:border-brand-300 hover:shadow-md">
                    <x-icon name="zap" class="size-6 text-accent-600" />
                    <h3 class="mt-3 font-semibold">{{ $s->name }}</h3>
                    <p class="mt-1 text-sm text-ink-600">{{ \Illuminate\Support\Str::limit($s->description, 110) }}</p>
                    @if($s->transitLabel())<p class="mt-3 text-xs font-medium text-brand-700">{{ $s->transitLabel() }}</p>@endif
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<section class="mx-auto grid max-w-7xl gap-8 px-4 py-16 sm:px-6 lg:grid-cols-2" aria-labelledby="cov">
    <div>
        <h2 id="cov" class="text-[length:var(--text-fluid-h2)]">Where we deliver</h2>
        @php $areas = $zones->count().' '.\Illuminate\Support\Str::plural('area', $zones->count()).($branchCount ? ' with '.$branchCount.' '.\Illuminate\Support\Str::plural('branch', $branchCount).' and pickup points' : ''); @endphp
        <p class="mt-2 text-ink-600">We currently serve {{ $areas }}.</p>
        <ul class="mt-6 flex flex-wrap gap-2">
            @forelse($zones->pluck('state')->unique() as $state)
                <li class="badge badge-neutral px-3 py-1 text-sm"><x-icon name="map-pin" class="size-3.5" />{{ $state }}</li>
            @empty
                <li class="text-ink-500">Coverage areas will be listed here once configured.</li>
            @endforelse
        </ul>
        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ route('coverage') }}" class="btn btn-secondary">Check coverage</a>
            <a href="{{ route('branches') }}" class="btn btn-ghost">Find a branch</a>
        </div>
    </div>
    <div class="card card-body bg-gradient-to-br from-brand-600 to-brand-800 text-white">
        <x-icon name="building" class="size-8 text-accent-300" />
        <h2 class="mt-4 text-2xl text-white">Shipping for a business?</h2>
        <p class="mt-2 text-brand-100">Upload many shipments at once from a spreadsheet, give your team role-based access, and get consolidated invoices and reports.</p>
        <ul class="mt-4 space-y-2 text-sm text-brand-50">
            <li class="flex gap-2"><x-icon name="check" class="size-5 text-accent-300" />Bulk CSV upload with validation preview</li>
            <li class="flex gap-2"><x-icon name="check" class="size-5 text-accent-300" />Team members with permissions</li>
            <li class="flex gap-2"><x-icon name="check" class="size-5 text-accent-300" />Negotiated rates and invoice terms (on approval)</li>
        </ul>
        <a href="{{ route('business.landing') }}" class="btn btn-accent mt-6 self-start">Open a business account</a>
    </div>
</section>

<section class="mx-auto max-w-7xl px-4 pb-8 sm:px-6">
    <div class="card card-body flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
        <div class="flex items-center gap-4">
            <span class="grid size-12 place-items-center rounded-full bg-accent-100 text-accent-700"><x-icon name="life-buoy" /></span>
            <div>
                <h2 class="text-lg">Need help with a delivery?</h2>
                <p class="text-ink-600">Our support team can help with delays, damaged parcels and payment questions.</p>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($p = \App\Support\Settings::get('support_phone'))<a href="tel:{{ $p }}" class="btn btn-secondary"><x-icon name="phone" class="size-4" />{{ $p }}</a>@endif
            <a href="{{ route('support.contact') }}" class="btn btn-primary">Contact support</a>
        </div>
    </div>
</section>
@endsection
