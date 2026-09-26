@props(['label', 'value', 'icon' => null, 'href' => null, 'hint' => null])
<{{ $href ? 'a' : 'div' }} @if($href) href="{{ $href }}" @endif class="stat block no-underline transition hover:no-underline {{ $href ? 'hover:border-brand-300 hover:shadow-md' : '' }}">
    <div class="flex items-start justify-between gap-2">
        <p class="stat-label">{{ $label }}</p>
        @if($icon)<span class="grid size-8 place-items-center rounded-lg bg-brand-50 text-brand-600"><x-icon :name="$icon" class="size-4" /></span>@endif
    </div>
    <p class="stat-value">{{ $value }}</p>
    @if($hint)<p class="mt-1 text-xs text-ink-500">{{ $hint }}</p>@endif
</{{ $href ? 'a' : 'div' }}>
