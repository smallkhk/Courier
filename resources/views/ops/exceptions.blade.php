@extends('layouts.portal', ['portal' => 'ops'])
@section('title', 'Exceptions & returns')
@section('content')
<x-page-header title="Exceptions & returns" />
<section class="card mb-6"><div class="card-header"><h2 class="text-base">Failed attempts awaiting a decision ({{ $attempts->count() }})</h2></div>
    @if($attempts->isEmpty())<x-empty icon="check-circle" title="Nothing waiting" />@else
    <ul class="divide-y divide-ink-100">@foreach($attempts as $at)
        <li class="grid gap-4 px-4 py-4 sm:px-6 lg:grid-cols-[1fr_1.4fr]">
            <div class="text-sm">
                <a href="{{ route('ops.shipments.show', $at->shipment) }}" class="font-mono font-semibold">{{ $at->shipment->tracking_number }}</a>
                <p class="mt-1"><x-pill tone="warning" icon="alert">{{ \App\Models\DeliveryAttempt::REASONS[$at->reason] }}</x-pill></p>
                <p class="mt-1 text-ink-600">{{ $at->attempted_at->diffForHumans() }} by {{ $at->rider?->name ?? 'staff' }} · {{ $at->shipment->recipient_name }}, <a href="tel:{{ $at->shipment->recipient_phone }}">{{ \App\Support\Phone::display($at->shipment->recipient_phone) }}</a></p>
                @if($at->note)<p class="mt-1">“{{ $at->note }}”</p>@endif
                <p class="text-xs text-ink-500">Attempts on this shipment: {{ \App\Models\DeliveryAttempt::where('shipment_id', $at->shipment_id)->count() }}</p>
            </div>
            <form method="post" action="{{ route('ops.attempts.resolve', $at) }}" class="grid gap-2 sm:grid-cols-[1fr_auto_auto] sm:items-end" x-data="{ r: '' }">
                @csrf
                <x-field name="resolution" label="Next step" type="select" required :options="\App\Models\DeliveryAttempt::RESOLUTIONS" x-model="r" />
                <div x-show="r === 'retry'" x-cloak><x-field name="retry_on" label="Retry on" type="date" :value="now()->addWeekday()->toDateString()" /></div>
                <button class="btn btn-primary" type="submit">Save</button>
                <div class="sm:col-span-3"><x-field name="note" label="Internal note (optional)" /></div>
            </form>
        </li>
    @endforeach</ul>@endif
</section>
<div class="grid gap-6 lg:grid-cols-2">
    @foreach(['On hold / exception' => $held, 'Returns in progress' => $returns] as $title => $list)
        <section class="card"><div class="card-header"><h2 class="text-base">{{ $title }} ({{ $list->count() }})</h2></div>
            @if($list->isEmpty())<p class="p-6 text-sm text-ink-500">None.</p>@else
            <ul class="divide-y divide-ink-100">@foreach($list as $s)<li class="flex justify-between gap-2 px-6 py-3 text-sm"><a href="{{ route('ops.shipments.show', $s) }}" class="font-mono">{{ $s->tracking_number }}</a><span class="flex items-center gap-2"><x-status-badge :status="$s->status" /><span class="text-xs text-ink-500">{{ $s->status_changed_at?->diffForHumans() }}</span></span></li>@endforeach</ul>@endif
        </section>
    @endforeach
</div>
@endsection
