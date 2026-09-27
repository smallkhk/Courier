@props(['image', 'title', 'eyebrow' => null, 'live' => false, 'from' => null, 'to' => null])
{{-- Dashboard banner: photo, animated delivery route and a live local clock. --}}
<section class="dash-hero" data-reveal>
    <img src="{{ asset('images/'.$image.'-sm.webp') }}" srcset="{{ asset('images/'.$image.'-sm.webp') }} 640w, {{ asset('images/'.$image.'.webp') }} 1024w" sizes="(min-width: 1024px) 70vw, 100vw" alt="">
    <div class="flex items-center justify-between gap-6 p-6 sm:p-8">
        <div class="hero-rise min-w-0">
            <p class="flex flex-wrap items-center gap-2 text-sm text-brand-100">
                @if($live)<span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-400/15 px-2.5 py-0.5 text-xs font-bold tracking-wider text-emerald-300 uppercase ring-1 ring-emerald-300/30"><span class="size-1.5 animate-pulse rounded-full bg-emerald-300"></span>Live</span>@endif
                <span x-data="liveClock(@js(\App\Support\Settings::get('timezone')), true)" x-text="text">{{ now()->timezone(\App\Support\Settings::get('timezone'))->format('l j F · g:i A') }}</span>
            </p>
            @if($eyebrow)<p class="mt-3 text-xs font-semibold tracking-widest text-accent-300 uppercase">{{ $eyebrow }}</p>@endif
            <h1 class="mt-1 text-2xl text-white sm:text-4xl">{{ $title }}</h1>
            @if(trim($slot))<div class="mt-5 flex flex-wrap gap-2">{{ $slot }}</div>@endif
        </div>
        <div class="hero-route hidden lg:block" aria-hidden="true">
            <svg width="320" height="130" viewBox="0 0 320 130" fill="none">
                <path d="M24 104 C 90 104, 110 40, 170 50 S 260 90, 292 30" stroke="rgb(255 255 255 / .35)" stroke-width="2.5" class="route-line"/>
                <circle cx="24" cy="104" r="16" fill="#34d399" opacity=".25" class="animate-ping-slow" style="transform-origin:24px 104px"/>
                <circle cx="24" cy="104" r="7" fill="#34d399" stroke="#fff" stroke-width="2"/>
                <path d="M292 6a12 12 0 0 1 12 12c0 9-12 22-12 22s-12-13-12-22a12 12 0 0 1 12-12z" fill="#fbcb5a" class="animate-float" style="transform-box:fill-box"/>
                <circle cx="292" cy="18" r="4.5" fill="#081640"/>
            </svg>
            @if($from)<span class="glass absolute bottom-0 left-0 rounded-full px-2.5 py-0.5 text-[11px] font-semibold text-white">{{ $from }}</span>@endif
            @if($to)<span class="glass absolute top-12 right-0 rounded-full px-2.5 py-0.5 text-[11px] font-semibold text-white">{{ $to }}</span>@endif
            <span class="hero-van"><x-icon name="truck" class="size-7" /></span>
        </div>
    </div>
</section>
