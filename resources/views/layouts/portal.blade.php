@php
    $brand = \App\Support\Settings::get('business_name');
    $nav = \App\Support\Navigation::for($portal, auth()->user());
    $portalNames = ['account' => 'My account', 'business' => 'Business', 'rider' => 'Rider', 'ops' => 'Operations'];
    $user = auth()->user();
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>@yield('title', $portalNames[$portal]) · {{ $brand }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <link rel="manifest" href="/manifest.webmanifest">
    <x-reveal-boot />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full" x-data="{ nav: false }">
<x-splash />
<a href="#main" class="skip-link">Skip to content</a>
@if(config('courier.payments.provider') === 'sandbox' && ! app()->environment('production'))
    <div class="bg-accent-300 px-4 py-1 text-center text-xs font-semibold text-ink-900" role="note">DEVELOPMENT MODE — sandbox payments, notifications logged only.</div>
@endif
<div class="portal lg:flex">
    <aside class="portal-side fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col overflow-y-auto transition-transform lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
           :class="nav && 'translate-x-0'" aria-label="{{ $portalNames[$portal] }} navigation">
        <div class="flex h-16 shrink-0 items-center justify-between border-b border-white/10 px-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-bold text-white no-underline hover:no-underline">
                <x-logo-mark size="size-9" />{{ $brand }}
            </a>
            <button class="btn btn-sm text-white hover:bg-white/10 lg:hidden" @click="nav=false" aria-label="Close menu"><x-icon name="x" /></button>
        </div>
        <div class="px-4 pt-4">
            <p class="portal-chip"><span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex size-2 rounded-full bg-emerald-400"></span></span>{{ $portalNames[$portal] }}{{ $portal === 'ops' ? ' · Control center' : '' }}</p>
        </div>
        <nav class="grid gap-0.5 p-3">
            @foreach($nav as $i => $item)
                @if(!empty($item['section']))<p class="mt-4 mb-1 px-3 text-[11px] font-semibold tracking-widest text-white/45 uppercase">{{ $item['section'] }}</p>@endif
                <a href="{{ route($item['route'], $item['params'] ?? []) }}" class="nav-link" style="--c: {{ \App\Support\Navigation::COLORS[$i % count(\App\Support\Navigation::COLORS)] }}" @if(\App\Support\Navigation::isActive($item)) aria-current="page" @endif><span class="nav-ico"><x-icon :name="$item['icon']" class="size-4" /></span>{{ $item['label'] }}</a>
            @endforeach
        </nav>
        @php
            $switch = [];
            if ($user->isStaff() && $portal !== 'ops') $switch[] = ['ops.dashboard', 'Operations portal'];
            if ($user->primaryMembership() && $portal !== 'business') $switch[] = ['business.dashboard', 'Business portal'];
            if ($user->isRole('customer') && $portal !== 'account') $switch[] = ['account.dashboard', 'Personal account'];
        @endphp
        @if($switch)
            <div class="border-t border-white/10 p-3">
                @foreach($switch as [$r, $l])<a href="{{ route($r) }}" class="nav-link" style="--c: #fbcb5a"><span class="nav-ico"><x-icon name="arrow-right" class="size-4" /></span>{{ $l }}</a>@endforeach
            </div>
        @endif
        {{-- Decorative: a van driving a dashed route between two pins. --}}
        <div class="side-scene mt-auto" aria-hidden="true">
            <svg viewBox="0 0 280 90" class="w-full" fill="none">
                <path d="M20 70 C 80 70, 90 25, 150 30 S 230 60, 262 22" stroke="rgb(255 255 255 / .28)" stroke-width="2" class="route-line"/>
                <circle cx="20" cy="70" r="5" fill="#34d399"/><circle cx="20" cy="70" r="10" fill="#34d399" opacity=".25" class="animate-ping-slow" style="transform-origin:20px 70px"/>
                <path d="M262 8a8 8 0 0 1 8 8c0 6-8 14-8 14s-8-8-8-14a8 8 0 0 1 8-8z" fill="#fbcb5a"/><circle cx="262" cy="16" r="3" fill="#081640"/>
            </svg>
            <span class="side-van"><x-icon name="truck" class="size-5" /></span>
        </div>
    </aside>
    <div x-cloak x-show="nav" class="fixed inset-0 z-40 bg-ink-900/60 backdrop-blur-sm lg:hidden" @click="nav=false"></div>

    <div class="portal-main relative isolate min-w-0 flex-1">
        <div class="portal-aurora" aria-hidden="true"><span></span><span></span><span></span></div>
        <header class="portal-header sticky top-0 z-30 flex h-16 items-center justify-between gap-3 px-4 sm:px-6">
            <button class="btn btn-ghost lg:hidden" @click="nav=true" aria-label="Open menu" :aria-expanded="nav.toString()"><x-icon name="menu" /></button>
            <form action="{{ route('track.lookup') }}" method="post" class="hidden max-w-sm flex-1 md:block" role="search" data-no-lock>
                @csrf
                <label for="quick-track" class="sr-only">Track a shipment</label>
                <div class="relative">
                    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-brand-500" />
                    <input id="quick-track" name="tracking_number" class="input rounded-full border-brand-100 bg-white/80 pl-9" placeholder="Track by tracking number" autocomplete="off">
                </div>
            </form>
            <div class="flex items-center gap-3">
                <span class="hidden items-center gap-2 rounded-full border border-brand-100 bg-white/80 px-3 py-1 text-xs font-semibold text-ink-700 tabular-nums xl:inline-flex" x-data="liveClock(@js(\App\Support\Settings::get('timezone')))"><x-icon name="clock" class="size-3.5 text-brand-600" /><span x-text="text"></span></span>
                <div class="hidden text-right sm:block">
                    <p class="text-sm font-semibold text-ink-900">{{ $user->name }}</p>
                    <p class="text-xs text-ink-500 capitalize">{{ $user->role }}</p>
                </div>
                <span class="avatar" aria-hidden="true">{{ collect(explode(' ', $user->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}</span>
                <form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-secondary btn-sm" type="submit"><x-icon name="logout" class="size-4" />Sign out</button></form>
            </div>
        </header>
        <main id="main" class="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
            <div class="mb-4"><x-flash /></div>
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
