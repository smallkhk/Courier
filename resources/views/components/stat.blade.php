@props(['label', 'value', 'icon' => null, 'href' => null, 'hint' => null, 'tone' => null])
@php
    // Colour-coded by meaning so a glance at the dashboard tells the story.
    $tones = [
        'brand' => ['#3461ee', '#1038b8'], 'sky' => ['#38bdf8', '#0369a1'], 'success' => ['#34d399', '#047857'],
        'danger' => ['#fb7185', '#be123c'], 'violet' => ['#a78bfa', '#6d28d9'], 'accent' => ['#fbbf24', '#b45309'],
        'teal' => ['#2dd4bf', '#0f766e'], 'orange' => ['#fb923c', '#c2410c'], 'pink' => ['#f472b6', '#be185d'],
    ];
    $byIcon = ['truck' => 'sky', 'check-circle' => 'success', 'alert' => 'danger', 'credit-card' => 'violet', 'calendar' => 'accent',
        'package' => 'brand', 'user' => 'orange', 'users' => 'orange', 'bike' => 'teal', 'undo' => 'pink', 'life-buoy' => 'violet',
        'wallet' => 'teal', 'receipt' => 'violet', 'chart' => 'brand', 'clock' => 'accent', 'building' => 'sky'];
    [$c1, $c2] = $tones[$tone ?? ($byIcon[$icon] ?? 'brand')] ?? $tones['brand'];
    $countable = is_int($value) || (is_string($value) && ctype_digit($value));
@endphp
<{{ $href ? 'a' : 'div' }} @if($href) href="{{ $href }}" @endif class="stat-tile" style="--c1: {{ $c1 }}; --c2: {{ $c2 }}" data-reveal>
    <div class="flex items-start justify-between gap-2">
        <p class="stat-label">{{ $label }}</p>
        @if($icon)<span class="stat-ico"><x-icon :name="$icon" class="size-5" /></span>@endif
    </div>
    <p class="stat-value text-3xl" @if($countable) x-data="countUp({{ (int) $value }})" x-text="shown" @endif>{{ $value }}</p>
    @if($hint)<p class="mt-1 text-xs text-ink-500">{{ $hint }}</p>@endif
    @if($icon)<x-icon :name="$icon" class="stat-watermark" />@endif
</{{ $href ? 'a' : 'div' }}>
