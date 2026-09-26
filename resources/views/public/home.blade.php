@extends('layouts.public')
@section('bare', true)
@section('content')
@php
    $brand = \App\Support\Settings::get('business_name');
    $states = $zones->pluck('state')->unique();
    $journey = [
        ['icon' => 'package', 'label' => 'Picked up', 'where' => 'Ikeja'],
        ['icon' => 'warehouse', 'label' => 'At sorting hub', 'where' => 'Ikeja hub'],
        ['icon' => 'truck', 'label' => 'In transit', 'where' => 'Third Mainland Bridge'],
        ['icon' => 'bike', 'label' => 'Out for delivery', 'where' => 'Lekki'],
        ['icon' => 'check', 'label' => 'Delivered', 'where' => 'Signed by recipient'],
    ];
@endphp

{{-- ================= HERO ================= --}}
<section class="photo-hero">
    <img src="{{ asset('images/hero-riders.webp') }}" srcset="{{ asset('images/hero-riders-sm.webp') }} 640w, {{ asset('images/hero-riders.webp') }} 1024w" sizes="100vw" alt="" fetchpriority="high">
    <div aria-hidden="true" class="pointer-events-none absolute -top-24 -right-24 -z-10 size-96 rounded-full bg-brand-500/30 blur-3xl"></div>
    <div aria-hidden="true" class="pointer-events-none absolute -bottom-32 left-1/3 -z-10 size-96 rounded-full bg-accent-400/20 blur-3xl"></div>

    <div class="mx-auto grid max-w-7xl items-center gap-10 px-4 pt-12 pb-20 sm:px-6 lg:grid-cols-[1.15fr_1fr] lg:pt-20 lg:pb-28" x-data="parallax">
        <div class="hero-rise">
            <div><x-flash /></div>
            <p class="glass inline-flex w-fit items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold text-brand-50">
                <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping-slow rounded-full bg-accent-300"></span><span class="relative inline-flex size-2 rounded-full bg-accent-400"></span></span>
                Same-day, express &amp; nationwide delivery
            </p>
            <h1 class="mt-5 text-[length:var(--text-fluid-h1)] leading-[1.05] font-extrabold tracking-tight text-white" x-data="rotatingWords(['across town', 'across the country', 'for your business', 'right to the door'])">
                Parcel delivery<br>
                <span class="sr-only">across town, across the country, for your business, right to the door</span>
                <span aria-hidden="true" class="relative inline-block min-h-[1.1em] text-white">
                    <template x-for="(w, n) in words" :key="n"><span x-show="i === n" class="word-in relative">
                        <span x-text="w"></span><span class="word-underline"></span></span></template>
                    <noscript>across town</noscript>
                </span><br>
                <span class="text-gradient">tracked every step.</span>
            </h1>
            <p class="mt-5 max-w-xl text-lg text-brand-100">
                Book a pickup in minutes, pay securely online, and follow your parcel from our rider's hands to a signed delivery at the door.
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('book.start') }}" class="btn btn-accent btn-lg btn-shine">Send a parcel <x-icon name="arrow-right" class="size-4" /></a>
                <a href="{{ route('pricing') }}" class="btn btn-on-dark btn-lg"><x-icon name="tag" class="size-4" />Get a price</a>
            </div>
            <ul class="mt-9 grid max-w-xl grid-cols-3 gap-3 text-sm text-brand-100">
                <li class="glass rounded-xl p-3"><x-icon name="shield" class="mb-1 size-5 text-accent-300" />Verified payments</li>
                <li class="glass rounded-xl p-3"><x-icon name="map-pin" class="mb-1 size-5 text-accent-300" />Step-by-step tracking</li>
                <li class="glass rounded-xl p-3"><x-icon name="check-circle" class="mb-1 size-5 text-accent-300" />Proof of delivery</li>
            </ul>
        </div>

        <div class="relative">
            <div class="card relative z-10 p-6 text-ink-800 shadow-xl transition-[translate] duration-300 ease-out sm:p-8" data-reveal="right" data-depth="10">
                <h2 class="flex items-center gap-2 text-xl"><x-icon name="search" class="size-5 text-brand-600" />Track a parcel</h2>
                <p class="mt-1 text-sm text-ink-600">Enter the tracking number from your receipt, email or SMS.</p>
                <form action="{{ route('track.lookup') }}" method="post" class="mt-5 space-y-3">
                    @csrf
                    <label for="hero-tracking" class="label">Tracking number</label>
                    <input id="hero-tracking" name="tracking_number" required maxlength="40" autocomplete="off" spellcheck="false"
                           class="input font-mono text-lg tracking-wider uppercase placeholder:normal-case placeholder:tracking-normal" placeholder="e.g. CX7K2M9QH4TRP8">
                    <button type="submit" class="btn btn-primary btn-lg w-full">Track shipment</button>
                </form>
            </div>

            {{-- Animated example journey (illustrative only; labelled as such). --}}
            <div class="relative z-20 -mt-4 ml-auto w-[92%] animate-float-slow rounded-2xl border border-white/15 bg-brand-900/90 p-4 text-white shadow-2xl backdrop-blur-md sm:-mt-6 sm:w-[85%]"
                 x-data="journeyDemo(@js($journey))" aria-hidden="true" data-depth="26" style="transition: translate .3s ease-out">
                <div class="flex items-center justify-between text-xs text-brand-100">
                    <span class="font-semibold tracking-wide uppercase">Example journey</span>
                    <span class="flex items-center gap-1.5"><span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping-slow rounded-full bg-success-200"></span><span class="relative inline-flex size-2 rounded-full bg-success-600"></span></span>Updating</span>
                </div>
                <div class="mt-3 flex items-center gap-3">
                    <span class="grid size-11 place-items-center rounded-xl bg-accent-400 text-ink-900 shadow-lg transition-transform duration-500" :class="i % 2 ? 'scale-105' : ''">
                        @foreach($journey as $k => $j)<span x-show="i === {{ $k }}" x-transition.opacity.duration.300ms><x-icon :name="$j['icon']" class="size-5" /></span>@endforeach
                    </span>
                    <div class="min-w-0">
                        <p class="font-semibold" x-text="stages[i].label"></p>
                        <p class="truncate text-xs text-brand-100" x-text="stages[i].where"></p>
                    </div>
                </div>
                <div class="mt-3 flex gap-1.5">
                    <template x-for="(s, n) in stages" :key="n">
                        <span class="h-1.5 flex-1 rounded-full transition-colors duration-500" :class="n <= i ? 'bg-accent-400' : 'bg-white/20'"></span>
                    </template>
                </div>
            </div>
        </div>
    </div>

    {{-- Animated road with a van driving across --}}
    <div aria-hidden="true" class="absolute inset-x-0 bottom-0 h-10 overflow-hidden">
        <svg class="absolute bottom-3 h-2 w-full" preserveAspectRatio="none" viewBox="0 0 100 2"><line x1="0" y1="1" x2="100" y2="1" stroke="rgb(255 255 255 / .35)" stroke-width="0.6" class="route-line" vector-effect="non-scaling-stroke" /></svg>
        <div class="drive absolute bottom-3.5 text-accent-300"><x-icon name="truck" class="size-7" /></div>
    </div>
</section>

{{-- ================= REAL NUMBERS ================= --}}
<section class="relative z-10 mx-auto -mt-8 max-w-5xl px-4 sm:px-6">
    <div class="card grid grid-cols-2 divide-ink-100 shadow-lg sm:grid-cols-4 sm:divide-x" data-reveal>
        @foreach([
            [$zones->count(), 'Coverage areas', 'globe'],
            [$states->count(), $states->count() === 1 ? 'State served' : 'States served', 'map-pin'],
            [$branchCount, 'Branches & pickup points', 'warehouse'],
            [$services->count(), 'Delivery services', 'zap'],
        ] as [$n, $label, $icon])
            <div class="flex items-center gap-3 p-5" x-data="countUp({{ (int) $n }})">
                <span class="grid size-10 place-items-center rounded-lg bg-brand-50 text-brand-600"><x-icon :name="$icon" class="size-5" /></span>
                <div><p class="text-2xl font-bold text-ink-900 tabular-nums" x-text="shown">{{ $n }}</p><p class="text-xs text-ink-500">{{ $label }}</p></div>
            </div>
        @endforeach
    </div>
</section>

{{-- ================= TICKER ================= --}}
<section class="marquee mt-12 overflow-hidden border-y border-ink-200 bg-white py-4" aria-label="Why customers choose us">
    <div class="marquee-track">
        @foreach([0, 1] as $copy)
            <ul class="flex shrink-0 items-center gap-10 pr-10" @if($copy) aria-hidden="true" @endif>
                @foreach([['shield', 'Payments verified with the provider'], ['map-pin', 'Tracking at every hand-off'], ['check-circle', 'Signed proof of delivery'], ['calendar', 'Scheduled pickups'], ['upload', 'Bulk uploads for business'], ['life-buoy', 'Real support team'], ['lock', 'Private delivery photos'], ['bike', 'Riders who know the city']] as [$ic, $txt])
                    <li class="flex items-center gap-2 text-sm font-semibold whitespace-nowrap text-ink-700"><span class="grid size-7 place-items-center rounded-full bg-brand-50 text-brand-600"><x-icon :name="$ic" class="size-4" /></span>{{ $txt }}</li>
                @endforeach
            </ul>
        @endforeach
    </div>
</section>

{{-- ================= HOW IT WORKS ================= --}}
<section class="mx-auto max-w-7xl px-4 py-20 sm:px-6" aria-labelledby="how">
    <div class="mx-auto max-w-2xl text-center" data-reveal>
        <p class="text-sm font-semibold tracking-wide text-brand-600 uppercase">How it works</p>
        <h2 id="how" class="mt-2 text-[length:var(--text-fluid-h2)]">From your door to theirs in three steps</h2>
    </div>
    <ol class="relative mt-14 grid gap-8 md:grid-cols-3">
        <svg aria-hidden="true" class="absolute top-7 left-[16%] hidden h-2 w-[68%] md:block" preserveAspectRatio="none" viewBox="0 0 100 2"><line x1="0" y1="1" x2="100" y2="1" stroke="var(--color-brand-300)" stroke-width="2" class="route-line" vector-effect="non-scaling-stroke"/></svg>
        @foreach([
            ['route', 'brand', 'Tell us where', 'Enter pickup and delivery details. We check the route is covered before you pay anything.'],
            ['wallet', 'accent', 'See the price, pay securely', 'A full price breakdown upfront. Payment is confirmed directly with the payment provider.'],
            ['track', 'success', 'Track to the door', 'Instant tracking number and updates at every step, through to a signed proof of delivery.'],
        ] as $i => [$ill, $tone, $title, $text])
            <li class="relative text-center" data-reveal style="--d: {{ $i * 120 }}ms">
                <div class="relative mx-auto w-fit">
                    <x-illus :name="$ill" :tone="$tone" size="size-16" class="mx-auto transition-transform duration-300 hover:scale-110 hover:-rotate-3" />
                    <span class="absolute -top-2 -right-2 grid size-7 place-items-center rounded-full bg-white text-xs font-bold text-ink-900 shadow-md ring-1 ring-ink-200">{{ $i + 1 }}</span>
                </div>
                <h3 class="mt-5 text-lg">{{ $title }}</h3>
                <p class="mx-auto mt-2 max-w-xs text-ink-600">{{ $text }}</p>
            </li>
        @endforeach
    </ol>
</section>

{{-- ================= DELIVERED WITH CARE (photo) ================= --}}
<section class="bg-white">
    <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 py-20 sm:px-6 lg:grid-cols-2">
        <div class="relative" data-reveal="left">
            <div class="grid grid-cols-5 gap-4">
                <img src="{{ asset('images/courier-boxes.webp') }}" alt="A smiling courier carrying a stack of parcels from a delivery van" loading="lazy" class="col-span-3 h-[420px] w-full rounded-3xl object-cover shadow-xl">
                <div class="col-span-2 flex flex-col gap-4 pt-10">
                    <img src="{{ asset('images/loading-parcels-sm.webp') }}" alt="A courier loading parcels into a vehicle" loading="lazy" class="h-48 w-full rounded-3xl object-cover shadow-lg">
                    <img src="{{ asset('images/warehouse-aisle-sm.webp') }}" alt="Parcels stacked in a sorting warehouse" loading="lazy" class="h-40 w-full rounded-3xl object-cover shadow-lg">
                </div>
            </div>
            <div class="absolute -bottom-6 left-6 flex animate-float items-center gap-3 rounded-2xl bg-white p-3 pr-5 shadow-xl ring-1 ring-ink-100" aria-hidden="true">
                <x-illus name="check" tone="success" size="size-11" />
                <div><p class="text-sm font-semibold text-ink-900">Proof of delivery</p><p class="text-xs text-ink-500">Signature, photo &amp; recipient name</p></div>
            </div>
        </div>
        <div data-reveal="right">
            <p class="text-sm font-semibold tracking-wide text-brand-600 uppercase">Delivered with care</p>
            <h2 class="mt-2 text-[length:var(--text-fluid-h2)]">Real people, real accountability</h2>
            <p class="mt-4 text-lg text-ink-600">Every parcel is handled by a named rider and logged at each hand-off, so you always know who has it and where it is.</p>
            <ul class="mt-8 space-y-5">
                @foreach([
                    ['bike', 'brand', 'Riders who know your city', 'Assigned by area and workload, reachable through our operations team.'],
                    ['shield', 'success', 'Secure, verified payments', 'Card, transfer or USSD — confirmed with the payment provider before we book.'],
                    ['support', 'accent', 'Help when you need it', 'Report a delay, damage or dispute and track the case to resolution.'],
                ] as $i => [$ill, $tone, $t, $d])
                    <li class="flex gap-4" data-reveal style="--d: {{ 100 + $i * 100 }}ms">
                        <x-illus :name="$ill" :tone="$tone" size="size-12" />
                        <div><h3 class="font-semibold">{{ $t }}</h3><p class="text-ink-600">{{ $d }}</p></div>
                    </li>
                @endforeach
            </ul>
            <a href="{{ route('book.start') }}" class="btn btn-primary btn-lg mt-8">Book a pickup <x-icon name="arrow-right" class="size-4" /></a>
        </div>
    </div>
</section>

{{-- ================= SERVICES ================= --}}
@if($services->isNotEmpty())
<section class="mx-auto max-w-7xl px-4 py-20 sm:px-6" aria-labelledby="svc">
    <div class="flex flex-wrap items-end justify-between gap-4" data-reveal>
        <div>
            <p class="text-sm font-semibold tracking-wide text-brand-600 uppercase">Services</p>
            <h2 id="svc" class="mt-2 text-[length:var(--text-fluid-h2)]">Choose the speed that fits</h2>
        </div>
        <a href="{{ route('services') }}" class="btn btn-secondary">All services <x-icon name="arrow-right" class="size-4" /></a>
    </div>
    <div class="mt-10 grid gap-5 sm:grid-cols-2 @if($services->count() >= 4) lg:grid-cols-4 @else lg:grid-cols-3 @endif">
        @foreach($services->take(4) as $i => $s)
            @php $tone = ['accent', 'brand', 'violet', 'sky'][$i % 4]; $ill = ['clock', 'track', 'box', 'globe'][$i % 4]; @endphp
            <article class="card hover-lift group relative overflow-hidden p-6" data-reveal style="--d: {{ $i * 90 }}ms">
                <div aria-hidden="true" class="absolute -top-10 -right-10 size-32 rounded-full bg-brand-50 transition-transform duration-500 group-hover:scale-150"></div>
                <div class="relative">
                    <x-illus :name="$ill" :tone="$tone" size="size-12" />
                    <h3 class="mt-4 text-lg">{{ $s->name }}</h3>
                    <p class="mt-1 text-sm text-ink-600">{{ \Illuminate\Support\Str::limit($s->description, 110) }}</p>
                    @if($s->transitLabel())<p class="mt-4"><x-pill tone="info" icon="clock">{{ $s->transitLabel() }}</x-pill></p>@endif
                </div>
            </article>
        @endforeach
    </div>
</section>
@endif

{{-- ================= BUSINESS (photo band) ================= --}}
<section class="photo-hero" aria-labelledby="biz">
    <img src="{{ asset('images/warehouse-aisle.webp') }}" alt="" loading="lazy">
    <div class="mx-auto grid max-w-7xl items-center gap-10 px-4 py-20 sm:px-6 lg:grid-cols-2">
        <div data-reveal="left">
            <p class="glass inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold text-brand-50"><x-icon name="building" class="size-3.5 text-accent-300" />For business</p>
            <h2 id="biz" class="mt-4 text-[length:var(--text-fluid-h2)] text-white">Shipping at volume? We've built the tools.</h2>
            <p class="mt-4 text-lg text-brand-100">Upload hundreds of shipments from a spreadsheet, give your team the right access, and get consolidated invoices and reports.</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('business.landing') }}" class="btn btn-accent btn-lg">Open a business account</a>
                <a href="{{ route('support.contact') }}" class="btn btn-on-dark btn-lg">Talk to us</a>
            </div>
        </div>
        <ul class="grid gap-4 sm:grid-cols-2">
            @foreach([['upload', 'Bulk CSV upload', 'Validated preview before anything is created'], ['team', 'Team permissions', 'Owners, shippers, finance and viewers'], ['invoice', 'Invoices', 'Pay per shipment or on approved terms'], ['chart', 'Reports & exports', 'Delivery performance and spend']] as $i => [$ill, $t, $d])
                <li class="glass hover-lift rounded-2xl p-5" data-reveal="zoom" style="--d: {{ $i * 90 }}ms">
                    <x-illus :name="$ill" :tone="['accent','sky','success','violet'][$i]" size="size-11" />
                    <h3 class="mt-3 font-semibold text-white">{{ $t }}</h3>
                    <p class="text-sm text-brand-100">{{ $d }}</p>
                </li>
            @endforeach
        </ul>
    </div>
</section>

{{-- ================= COVERAGE ================= --}}
<section class="mx-auto grid max-w-7xl items-center gap-10 px-4 py-20 sm:px-6 lg:grid-cols-[1fr_1.1fr]" aria-labelledby="cov">
    <div data-reveal="left">
        <p class="text-sm font-semibold tracking-wide text-brand-600 uppercase">Coverage</p>
        <h2 id="cov" class="mt-2 text-[length:var(--text-fluid-h2)]">Where we deliver</h2>
        <p class="mt-3 text-ink-600">Routes are checked automatically when you book — no surprises after you pay.</p>
        <ul class="mt-6 flex flex-wrap gap-2">
            @forelse($states as $state)
                <li class="badge badge-neutral px-3 py-1.5 text-sm transition hover:border-brand-300 hover:bg-brand-50"><x-icon name="map-pin" class="size-3.5 text-brand-600" />{{ $state }}</li>
            @empty
                <li class="text-ink-500">Coverage areas will be listed here once configured.</li>
            @endforelse
        </ul>
        <div class="mt-8 flex flex-wrap gap-3">
            <a href="{{ route('coverage') }}" class="btn btn-primary">Check coverage</a>
            <a href="{{ route('branches') }}" class="btn btn-secondary"><x-icon name="warehouse" class="size-4" />Find a branch</a>
        </div>
    </div>
    <div class="relative overflow-hidden rounded-3xl shadow-xl" data-reveal="right">
        <img src="{{ asset('images/highway.webp') }}" alt="Delivery trucks travelling on a highway at dusk" loading="lazy" class="h-80 w-full object-cover transition-transform duration-[2s] hover:scale-105 lg:h-96">
        <div class="absolute inset-0 bg-gradient-to-t from-brand-950/80 via-brand-950/10 to-transparent"></div>
        <svg aria-hidden="true" class="absolute inset-0 size-full" viewBox="0 0 400 300" preserveAspectRatio="none">
            <path d="M40 250 C 120 200, 160 120, 250 130 S 360 60, 370 40" fill="none" stroke="#fbcb5a" stroke-width="3" class="route-line"/>
        </svg>
        <span aria-hidden="true" class="absolute bottom-[14%] left-[9%] grid size-5 place-items-center"><span class="absolute size-full animate-ping-slow rounded-full bg-accent-300"></span><span class="size-3 rounded-full bg-accent-400 ring-2 ring-white"></span></span>
        <span aria-hidden="true" class="absolute top-[10%] right-[6%] grid size-5 place-items-center"><span class="absolute size-full animate-ping-slow rounded-full bg-success-200"></span><span class="size-3 rounded-full bg-success-600 ring-2 ring-white"></span></span>
        <p class="absolute bottom-4 left-5 text-sm font-semibold text-white">{{ $zones->count() }} coverage {{ \Illuminate\Support\Str::plural('area', $zones->count()) }}@if($branchCount) · {{ $branchCount }} {{ \Illuminate\Support\Str::plural('branch', $branchCount) }}@endif</p>
    </div>
</section>

{{-- ================= FINAL CTA ================= --}}
<section class="mx-auto max-w-7xl px-4 pb-8 sm:px-6">
    <div class="photo-hero rounded-3xl shadow-xl" data-reveal="zoom">
        <img src="{{ asset('images/truck-night.webp') }}" alt="" loading="lazy">
        <div class="flex flex-col items-start justify-between gap-6 px-6 py-12 sm:px-12 md:flex-row md:items-center">
            <div>
                <h2 class="text-3xl text-white">Ready to send your first parcel?</h2>
                <p class="mt-2 text-brand-100">Get a price in seconds{{ \App\Support\Settings::get('guest_booking_enabled') ? ' — no account needed' : '' }}.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('book.start') }}" class="btn btn-accent btn-lg btn-shine">Send a parcel <x-icon name="arrow-right" class="size-4" /></a>
                @if($p = \App\Support\Settings::get('support_phone'))<a href="tel:{{ $p }}" class="btn btn-on-dark btn-lg"><x-icon name="phone" class="size-4" />{{ $p }}</a>@endif
            </div>
        </div>
    </div>
</section>
@endsection
