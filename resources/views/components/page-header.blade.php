@props(['title', 'subtitle' => null, 'back' => null, 'icon' => null])
@php
    // Portal pages pick up the same colour-coded icon as their sidebar entry.
    $nav = $icon ? ['icon' => $icon, 'color' => '#3461ee'] : \App\Support\Navigation::current(auth()->user());
@endphp
<div class="mb-6 flex flex-wrap items-end justify-between gap-4" data-reveal>
    <div class="flex min-w-0 items-center gap-4">
        @if($nav)
            <span class="hidden size-12 shrink-0 place-items-center rounded-2xl text-white shadow-lg sm:grid" style="background: linear-gradient(135deg, {{ $nav['color'] }}, color-mix(in srgb, {{ $nav['color'] }} 55%, #081640)); box-shadow: 0 10px 20px -8px {{ $nav['color'] }}" aria-hidden="true"><x-icon :name="$nav['icon']" class="size-6" /></span>
        @endif
        <div class="min-w-0">
            @if($back)<a href="{{ $back }}" class="mb-1 inline-flex items-center gap-1 text-sm text-ink-600"><span aria-hidden="true">←</span> Back</a>@endif
            <h1 class="text-2xl sm:text-3xl">{{ $title }}</h1>
            @if($subtitle)<p class="mt-1 text-ink-600">{{ $subtitle }}</p>@endif
        </div>
    </div>
    @if(trim($slot))<div class="flex flex-wrap gap-2">{{ $slot }}</div>@endif
</div>
