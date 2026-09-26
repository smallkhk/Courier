@props(['title', 'subtitle' => null, 'back' => null])
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div class="min-w-0">
        @if($back)<a href="{{ $back }}" class="mb-2 inline-flex items-center gap-1 text-sm text-ink-600"><span aria-hidden="true">←</span> Back</a>@endif
        <h1 class="text-2xl sm:text-3xl">{{ $title }}</h1>
        @if($subtitle)<p class="mt-1 text-ink-600">{{ $subtitle }}</p>@endif
    </div>
    @if(trim($slot))<div class="flex flex-wrap gap-2">{{ $slot }}</div>@endif
</div>
