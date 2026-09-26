@extends('layouts.public')
@section('title', 'Services')
@section('content')
<x-page-header title="Delivery services" subtitle="Service availability depends on the pickup and delivery areas. Delivery times are estimates, not guarantees." />
<div class="grid gap-6 md:grid-cols-2">
    @forelse($services as $s)
        <article class="card card-body">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="grid size-11 place-items-center rounded-xl bg-brand-50 text-brand-600"><x-icon name="zap" /></span>
                    <h2 class="text-xl">{{ $s->name }}</h2>
                </div>
                @if($s->transitLabel())<x-pill tone="info" icon="clock">{{ $s->transitLabel() }}</x-pill>@endif
            </div>
            <p class="mt-4 text-ink-700">{{ $s->description }}</p>
            @if($s->max_weight_kg)<p class="mt-2 text-sm text-ink-600">Up to {{ rtrim(rtrim($s->max_weight_kg, '0'), '.') }} kg chargeable weight.</p>@endif
            <p class="mt-3 text-sm text-ink-600"><strong>Available in:</strong> {{ $s->zones->pluck('name')->join(', ') ?: 'No areas configured yet' }}</p>
            <div class="mt-5 flex gap-2">
                <a href="{{ route('pricing') }}" class="btn btn-secondary btn-sm">Get a price</a>
                <a href="{{ route('book.start') }}" class="btn btn-primary btn-sm">Book now</a>
            </div>
        </article>
    @empty
        <div class="card md:col-span-2"><x-empty icon="layers" title="No services published yet">Services appear here once an administrator configures them.</x-empty></div>
    @endforelse
</div>
@endsection
