@php $brand = \App\Support\Settings::get('business_name'); @endphp
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('title')@yield('title') · @endif{{ $brand }}</title>
    <meta name="description" content="@yield('description', 'Book parcel pickup and delivery, get instant quotes and track shipments with '.$brand.'.')">
    <meta name="theme-color" content="#0f2f8f">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full flex-col">
<a href="#main" class="skip-link">Skip to content</a>
@if(config('courier.payments.provider') === 'sandbox' && ! app()->environment('production'))
    <div class="bg-accent-300 px-4 py-1.5 text-center text-xs font-semibold text-ink-900" role="note">DEVELOPMENT MODE — payments use a sandbox, no real money moves. Emails/SMS are logged, not sent.</div>
@endif
<header class="sticky top-0 z-40 border-b border-ink-200 bg-white/95 backdrop-blur" x-data="{ open: false }">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6">
        <a href="{{ route('home') }}" class="flex items-center gap-2 text-lg font-bold text-ink-900 no-underline hover:no-underline">
            <span class="grid size-9 place-items-center rounded-lg bg-brand-600 text-white"><x-icon name="package" class="size-5" /></span>
            {{ $brand }}
        </a>
        <nav class="hidden items-center gap-1 lg:flex" aria-label="Main">
            @foreach(['services' => 'Services', 'pricing' => 'Pricing', 'coverage' => 'Coverage', 'branches' => 'Branches', 'business.landing' => 'Business', 'faq' => 'Help'] as $r => $l)
                <a href="{{ route($r) }}" class="nav-link" @if(request()->routeIs($r)) aria-current="page" @endif>{{ $l }}</a>
            @endforeach
        </nav>
        <div class="hidden items-center gap-2 lg:flex">
            <a href="{{ route('track.form') }}" class="btn btn-ghost"><x-icon name="search" class="size-4" />Track</a>
            @auth
                <a href="{{ route('portal') }}" class="btn btn-secondary">My account</a>
            @else
                <a href="{{ route('login') }}" class="btn btn-ghost">Sign in</a>
            @endauth
            <a href="{{ route('book.start') }}" class="btn btn-primary">Send a parcel</a>
        </div>
        <button type="button" class="btn btn-ghost lg:hidden" @click="open = !open" :aria-expanded="open.toString()" aria-controls="mobile-nav" aria-label="Menu">
            <x-icon name="menu" />
        </button>
    </div>
    <nav id="mobile-nav" x-cloak x-show="open" x-transition class="border-t border-ink-200 bg-white px-4 pb-4 lg:hidden" aria-label="Mobile">
        <div class="grid gap-1 py-2">
            @foreach(['track.form' => 'Track a parcel', 'services' => 'Services', 'pricing' => 'Pricing & quote', 'coverage' => 'Coverage areas', 'branches' => 'Branches & pickup points', 'business.landing' => 'For business', 'faq' => 'Help & FAQs', 'support.contact' => 'Contact us'] as $r => $l)
                <a href="{{ route($r) }}" class="nav-link">{{ $l }}</a>
            @endforeach
        </div>
        <div class="grid grid-cols-2 gap-2">
            @auth<a href="{{ route('portal') }}" class="btn btn-secondary">My account</a>@else<a href="{{ route('login') }}" class="btn btn-secondary">Sign in</a>@endauth
            <a href="{{ route('book.start') }}" class="btn btn-primary">Send a parcel</a>
        </div>
    </nav>
</header>

<main id="main" class="flex-1">
    @hasSection('bare')
        @yield('content')
    @else
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-12">
            <x-flash />
            <div class="mt-4">@yield('content')</div>
        </div>
    @endif
</main>

<footer class="mt-16 bg-ink-900 text-ink-300">
    <div class="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:px-6 md:grid-cols-4">
        <div>
            <p class="flex items-center gap-2 text-lg font-bold text-white"><span class="grid size-8 place-items-center rounded-lg bg-brand-600"><x-icon name="package" class="size-4" /></span>{{ $brand }}</p>
            <p class="mt-3 text-sm">Parcel pickup, delivery and tracking.</p>
            @if($addr = \App\Support\Settings::get('office_address'))<p class="mt-3 text-sm">{{ $addr }}</p>@endif
        </div>
        <div>
            <h2 class="text-sm font-semibold text-white">Ship</h2>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a class="text-ink-300" href="{{ route('book.start') }}">Send a parcel</a></li>
                <li><a class="text-ink-300" href="{{ route('pricing') }}">Get a quote</a></li>
                <li><a class="text-ink-300" href="{{ route('track.form') }}">Track a parcel</a></li>
                <li><a class="text-ink-300" href="{{ route('business.landing') }}">Business accounts</a></li>
            </ul>
        </div>
        <div>
            <h2 class="text-sm font-semibold text-white">Company</h2>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a class="text-ink-300" href="{{ route('about') }}">About us</a></li>
                <li><a class="text-ink-300" href="{{ route('branches') }}">Branches</a></li>
                <li><a class="text-ink-300" href="{{ route('coverage') }}">Coverage</a></li>
                <li><a class="text-ink-300" href="{{ route('faq') }}">FAQs</a></li>
            </ul>
        </div>
        <div>
            <h2 class="text-sm font-semibold text-white">Help</h2>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a class="text-ink-300" href="{{ route('support.contact') }}">Contact & support</a></li>
                @if($e = \App\Support\Settings::get('support_email'))<li><a class="text-ink-300" href="mailto:{{ $e }}">{{ $e }}</a></li>@endif
                @if($p = \App\Support\Settings::get('support_phone'))<li><a class="text-ink-300" href="tel:{{ $p }}">{{ $p }}</a></li>@endif
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="mx-auto flex max-w-7xl flex-wrap justify-between gap-3 px-4 py-4 text-xs sm:px-6">
            <p>© {{ date('Y') }} {{ $brand }}</p>
            <p class="flex gap-4">
                <a class="text-ink-300" href="{{ route('legal', 'terms') }}">Terms</a>
                <a class="text-ink-300" href="{{ route('legal', 'privacy') }}">Privacy</a>
                <a class="text-ink-300" href="{{ route('legal', 'delivery-policy') }}">Delivery & claims policy</a>
            </p>
        </div>
    </div>
</footer>
</body>
</html>
