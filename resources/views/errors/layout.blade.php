@php
    try { $brand = \App\Support\Settings::get('business_name'); } catch (\Throwable) { $brand = config('app.name'); }
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · {{ $brand }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-full flex-col bg-gradient-to-b from-brand-950 via-brand-900 to-brand-800 text-white">
    <header class="mx-auto flex w-full max-w-5xl items-center justify-between px-4 py-5 sm:px-6">
        <a href="/" class="flex items-center gap-2 text-lg font-bold text-white no-underline hover:no-underline"><x-logo-mark size="size-9" />{{ $brand }}</a>
    </header>
    <main class="mx-auto flex w-full max-w-3xl flex-1 flex-col items-center justify-center px-4 pb-10 text-center sm:px-6">
        <p class="text-7xl font-black tracking-tighter text-white/15 sm:text-9xl">@yield('code')</p>
        {{-- Animated scene: rider on a scooter with a parcel, city scrolling behind --}}
        <div class="relative -mt-10 h-48 w-full max-w-xl overflow-hidden sm:-mt-16" aria-hidden="true">
            <svg class="scene-city absolute bottom-10 h-28 w-[200%] text-white/10" viewBox="0 0 800 110" preserveAspectRatio="none">
                @foreach([0, 400] as $o)
                    <g transform="translate({{ $o }} 0)" fill="currentColor">
                        <rect x="10" y="40" width="40" height="70"/><rect x="55" y="15" width="30" height="95"/><rect x="92" y="55" width="45" height="55"/>
                        <rect x="142" y="25" width="26" height="85"/><rect x="175" y="60" width="50" height="50"/><rect x="232" y="5" width="34" height="105"/>
                        <rect x="272" y="45" width="42" height="65"/><rect x="320" y="30" width="28" height="80"/><rect x="354" y="65" width="40" height="45"/>
                    </g>
                @endforeach
            </svg>
            <div class="absolute inset-x-0 bottom-9 h-0.5 bg-[repeating-linear-gradient(90deg,rgb(255_255_255/.35)_0_16px,transparent_16px_28px)] [animation:road_.5s_linear_infinite]"></div>
            <svg class="scene-rider absolute bottom-9 left-1/2 h-32 -translate-x-1/2" viewBox="0 0 160 120">
                <rect x="84" y="20" width="36" height="30" rx="4" fill="#f5b41e" stroke="#b87a06" stroke-width="2"/>
                <path d="M84 30h36M102 20v30" stroke="#b87a06" stroke-width="2"/>
                <circle cx="66" cy="22" r="11" fill="#fff"/><path d="M56 20a10 10 0 0 1 20 0" fill="#1447e6"/>
                <path d="M62 34l-8 30h22l14-18" fill="none" stroke="#fff" stroke-width="9" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M30 80h60l18-26h14" fill="none" stroke="#5b81f4" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M26 80c0-10 8-16 20-16h26" fill="none" stroke="#1447e6" stroke-width="10" stroke-linecap="round"/>
                <g class="scene-wheel"><circle cx="34" cy="96" r="16" fill="none" stroke="#fff" stroke-width="6"/><path d="M34 82v28M20 96h28" stroke="#fff" stroke-width="2.5"/></g>
                <g class="scene-wheel"><circle cx="118" cy="96" r="16" fill="none" stroke="#fff" stroke-width="6"/><path d="M118 82v28M104 96h28" stroke="#fff" stroke-width="2.5"/></g>
                <path d="M4 70h14M0 82h12M6 94h10" stroke="#fbcb5a" stroke-width="3" stroke-linecap="round" opacity=".7"/>
            </svg>
        </div>
        <h1 class="mt-2 text-3xl font-bold text-white sm:text-4xl">@yield('heading')</h1>
        <p class="mt-3 max-w-lg text-lg text-brand-100">@yield('message')</p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            @section('actions')
                <a href="/" class="btn btn-accent btn-lg">Back to home</a>
                <a href="/track" class="btn btn-on-dark btn-lg">Track a parcel</a>
            @show
        </div>
    </main>
</body>
</html>
