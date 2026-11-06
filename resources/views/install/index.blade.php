<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Set up ExpressShipping</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full">
@php $ready = collect($requirements)->every(fn ($r) => $r[1]); @endphp
<section class="photo-hero">
    <img src="{{ asset('images/truck-night.webp') }}" alt="">
    <div class="hero-rise mx-auto max-w-3xl px-4 py-12 sm:px-6">
        <p class="glass inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold text-brand-50"><x-icon name="zap" class="size-3.5 text-accent-300" />One-time setup</p>
        <h1 class="mt-4 text-3xl font-bold text-white sm:text-4xl">Welcome to ExpressShipping</h1>
        <p class="mt-3 text-lg text-brand-100">This creates your database tables and your administrator account. It takes about a minute and locks itself when it's done.</p>
    </div>
</section>
<main class="mx-auto -mt-6 max-w-3xl space-y-6 px-4 pb-16 sm:px-6">
    <x-flash />
    <section class="card" aria-labelledby="req-h">
        <div class="card-header"><h2 id="req-h" class="text-base">1. Server checks</h2>@if($ready)<span class="badge badge-success">All good</span>@else<a class="text-sm" href="{{ url('/install') }}">Re-check</a>@endif</div>
        <ul class="divide-y divide-ink-100">
            @foreach($requirements as [$label, $ok, $help])
                <li class="flex gap-3 px-4 py-3 text-sm sm:px-6">
                    <x-icon :name="$ok ? 'check-circle' : 'alert'" class="mt-0.5 size-5 {{ $ok ? 'text-success-600' : 'text-danger-600' }}" />
                    <div><p class="font-medium text-ink-900">{{ $label }}</p>@unless($ok)<p class="mt-0.5 text-ink-600">{{ $help }}</p>@endunless</div>
                </li>
            @endforeach
        </ul>
    </section>

    <form method="post" action="{{ route('install.store') }}" class="card" x-data="{ busy: false }" @submit="busy = true">
        @csrf
        <div class="card-header"><h2 class="text-base">2. Your business and administrator</h2></div>
        <div class="card-body grid gap-4 sm:grid-cols-2">
            <x-field name="install_token" label="Setup code" type="password" required hint="The INSTALL_TOKEN value from your .env file." class="sm:col-span-2" autocomplete="off" />
            <x-field name="business_name" label="Business name" :value="old('business_name', 'ExpressShipping')" required />
            <x-field name="support_email" label="Customer support email" type="email" required />
            <x-field name="name" label="Your full name" required autocomplete="name" />
            <x-field name="email" label="Your email (admin sign-in)" type="email" required autocomplete="email" />
            <x-field name="password" label="Password" type="password" required hint="At least 12 characters, letters and numbers." autocomplete="new-password" />
            <x-field name="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password" />
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-ink-200 px-4 py-4 sm:px-6">
            <p class="text-xs text-ink-500">No demo or sample data is created.</p>
            <button class="btn btn-primary btn-lg" type="submit" @disabled(! $ready) :disabled="busy">
                <span x-show="!busy">Install ExpressShipping</span>
                <span x-show="busy" x-cloak class="inline-flex items-center gap-2"><span class="spinner"></span>Installing…</span>
            </button>
        </div>
    </form>
</main>
</body>
</html>
