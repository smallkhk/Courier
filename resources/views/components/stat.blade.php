@props(['label', 'value', 'icon' => null, 'href' => null, 'hint' => null])
<{{ $href ? 'a' : 'div' }} @if($href) href="{{ $href }}" @endif class="stat block no-underline hover:no-underline {{ $href ? 'hover-lift hover:border-brand-300' : '' }}" data-reveal>
    <div class="flex items-start justify-between gap-2">
        <p class="stat-label">{{ $label }}</p>
        @if($icon)<span class="grid size-9 place-items-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-md"><x-icon :name="$icon" class="size-4" /></span>@endif
    </div>
    <p class="stat-value">{{ $value }}</p>
    @if($hint)<p class="mt-1 text-xs text-ink-500">{{ $hint }}</p>@endif
</{{ $href ? 'a' : 'div' }}>
