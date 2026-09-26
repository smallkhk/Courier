@props(['events', 'internal' => false])
{{-- Chronological (newest first) event list. Internal notes render only when $internal is true. --}}
<ol class="relative space-y-6 border-l-2 border-ink-200 pl-6" aria-label="Shipment timeline">
    @forelse($events->sortByDesc(fn ($e) => [$e->occurred_at, $e->id]) as $e)
        <li class="relative">
            <span class="absolute -left-[35px] grid size-7 place-items-center rounded-full border-2 border-white {{ $loop->first ? 'bg-brand-600 text-white' : 'bg-ink-200 text-ink-600' }}">
                <x-icon :name="$e->status->icon()" class="size-3.5" />
            </span>
            <div class="flex flex-wrap items-center gap-2">
                <p class="font-semibold text-ink-900">{{ $e->status->label() }}</p>
                @if($loop->first)<span class="badge badge-info">Latest</span>@endif
            </div>
            <p class="text-sm text-ink-700">{{ $e->public_description }}</p>
            <p class="mt-0.5 text-xs text-ink-500">
                <time datetime="{{ $e->occurred_at->toIso8601String() }}">{{ $e->occurred_at->timezone(\App\Support\Settings::get('timezone'))->format('D j M Y, g:i a') }}</time>
                @if($e->location) · {{ $e->location }} @endif
                @if($internal) · <span class="uppercase">{{ $e->source }}</span>@if($e->actor) · {{ $e->actor->name }}@endif @endif
            </p>
            @if($internal && $e->internal_note)
                <p class="mt-1 rounded bg-warning-50 px-2 py-1 text-xs text-warning-800"><strong>Internal:</strong> {{ $e->internal_note }}</p>
            @endif
        </li>
    @empty
        <li class="text-sm text-ink-500">No tracking events yet.</li>
    @endforelse
</ol>
