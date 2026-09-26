@php $steps = ['Details', 'Review & confirm', 'Payment', 'Done']; @endphp
<ol class="mb-8 flex flex-wrap items-center gap-2 text-sm" aria-label="Booking progress">
    @foreach($steps as $i => $s)
        <li class="flex items-center gap-2 {{ $i + 1 === $current ? 'font-semibold text-ink-900' : ($i + 1 < $current ? 'text-success-700' : 'text-ink-500') }}" @if($i + 1 === $current) aria-current="step" @endif>
            <span class="grid size-7 place-items-center rounded-full text-xs {{ $i + 1 === $current ? 'bg-brand-600 text-white' : ($i + 1 < $current ? 'bg-success-50 text-success-700' : 'bg-ink-100') }}">
                @if($i + 1 < $current)<x-icon name="check" class="size-3.5" />@else{{ $i + 1 }}@endif
            </span>{{ $s }}
        </li>
        @if(! $loop->last)<li aria-hidden="true" class="h-px w-6 bg-ink-300"></li>@endif
    @endforeach
</ol>
