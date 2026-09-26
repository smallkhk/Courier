@props(['image', 'title', 'subtitle' => null, 'eyebrow' => null, 'icon' => null, 'tall' => false])
{{-- Full-width photo banner for inner public pages. --}}
<section class="photo-hero">
    <img src="{{ asset('images/'.$image.'.webp') }}" srcset="{{ asset('images/'.$image.'-sm.webp') }} 640w, {{ asset('images/'.$image.'.webp') }} 1024w" sizes="100vw" alt="" fetchpriority="high">
    <div class="hero-rise mx-auto max-w-7xl px-4 py-14 sm:px-6 sm:py-20 {{ $tall ? 'pb-32 sm:pb-40' : '' }}">
        @if($eyebrow)<p class="glass inline-flex w-fit items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold text-brand-50">@if($icon)<x-icon :name="$icon" class="size-3.5 text-accent-300" />@endif{{ $eyebrow }}</p>@endif
        <h1 class="mt-4 max-w-3xl text-[length:var(--text-fluid-h1)] leading-[1.08] font-bold text-white">{{ $title }}</h1>
        @if($subtitle)<p class="mt-4 max-w-2xl text-lg text-brand-100">{{ $subtitle }}</p>@endif
        @if(trim($slot))<div class="mt-7 flex flex-wrap gap-3">{{ $slot }}</div>@endif
    </div>
</section>
