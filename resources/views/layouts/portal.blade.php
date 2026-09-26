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
<div class="lg:flex">
    <aside class="fixed inset-y-0 left-0 z-50 w-72 -translate-x-full overflow-y-auto border-r border-ink-200 bg-white transition-transform lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
           :class="nav && 'translate-x-0'" aria-label="{{ $portalNames[$portal] }} navigation">
        <div class="flex h-16 items-center justify-between border-b border-ink-200 px-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-bold text-ink-900 no-underline">
                <x-logo-mark size="size-8" />{{ $brand }}
            </a>
            <button class="btn btn-ghost btn-sm lg:hidden" @click="nav=false" aria-label="Close menu"><x-icon name="x" /></button>
        </div>
        <div class="px-4 pt-4">
            <p class="text-xs font-semibold tracking-wide text-ink-500 uppercase">{{ $portalNames[$portal] }}</p>
        </div>
        <nav class="grid gap-0.5 p-3">
            @foreach($nav as $item)
                @if(!empty($item['section']))<p class="mt-4 mb-1 px-3 text-xs font-semibold tracking-wide text-ink-500 uppercase">{{ $item['section'] }}</p>@endif
                @php
                    $url = route($item['route'], $item['params'] ?? []);
                    $active = isset($item['params']) ? request()->url() === $url || str_starts_with(request()->url(), $url.'/') : request()->routeIs($item['match'] ?? $item['route']);
                @endphp
                <a href="{{ $url }}" class="nav-link" @if($active) aria-current="page" @endif><x-icon :name="$item['icon']" class="size-4.5" />{{ $item['label'] }}</a>
            @endforeach
        </nav>
        @php
            $switch = [];
            if ($user->isStaff() && $portal !== 'ops') $switch[] = ['ops.dashboard', 'Operations portal'];
            if ($user->primaryMembership() && $portal !== 'business') $switch[] = ['business.dashboard', 'Business portal'];
            if ($user->isRole('customer') && $portal !== 'account') $switch[] = ['account.dashboard', 'Personal account'];
        @endphp
        @if($switch)
            <div class="border-t border-ink-200 p-3">
                @foreach($switch as [$r, $l])<a href="{{ route($r) }}" class="nav-link"><x-icon name="arrow-right" class="size-4" />{{ $l }}</a>@endforeach
            </div>
        @endif
    </aside>
    <div x-cloak x-show="nav" class="fixed inset-0 z-40 bg-ink-900/50 lg:hidden" @click="nav=false"></div>

    <div class="min-w-0 flex-1">
        <header class="sticky top-0 z-30 flex h-16 items-center justify-between gap-3 border-b border-ink-200 bg-white/95 px-4 backdrop-blur sm:px-6">
            <button class="btn btn-ghost lg:hidden" @click="nav=true" aria-label="Open menu" :aria-expanded="nav.toString()"><x-icon name="menu" /></button>
            <form action="{{ route('track.lookup') }}" method="post" class="hidden max-w-sm flex-1 md:block" role="search" data-no-lock>
                @csrf
                <label for="quick-track" class="sr-only">Track a shipment</label>
                <div class="relative">
                    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-400" />
                    <input id="quick-track" name="tracking_number" class="input pl-9" placeholder="Track by tracking number" autocomplete="off">
                </div>
            </form>
            <div class="flex items-center gap-3" x-data="{ open: false }">
                <div class="hidden text-right sm:block">
                    <p class="text-sm font-semibold text-ink-900">{{ $user->name }}</p>
                    <p class="text-xs text-ink-500 capitalize">{{ $user->role }}</p>
                </div>
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
