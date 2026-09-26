@extends('layouts.public')
@section('title', 'Booking confirmed')
@section('content')
@php $pending = $shipment->status->value === 'pending_payment'; @endphp
<div class="mx-auto max-w-2xl">
    @include('booking._steps', ['current' => $pending ? 3 : 4])
    <div class="card card-body text-center" @unless($pending) x-data x-init="setTimeout(() => window.confettiBurst($refs.badge), 350)" @endunless>
        @if($pending)
            <span class="mx-auto grid size-16 place-items-center rounded-full bg-warning-50 text-warning-800"><x-icon name="clock" class="size-8" /></span>
        @else
            <span x-ref="badge" class="splash-logo-tile mx-auto grid size-20 place-items-center rounded-full bg-gradient-to-br from-success-600 to-success-700 text-white shadow-lg ring-8 ring-success-50">
                <svg class="splash-logo size-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path pathLength="100" d="M20 6 9 17l-5-5"/></svg>
            </span>
        @endif
        <h1 class="mt-4 text-2xl">{{ $pending ? 'Awaiting payment' : 'Your shipment is booked' }}</h1>
        <p class="mt-2 text-ink-600">Keep this tracking number — we've also emailed it to {{ $shipment->sender_email ?? 'you' }}.</p>
        <p class="mx-auto mt-5 w-fit animate-float rounded-xl border-2 border-dashed border-brand-300 bg-brand-50 px-6 py-3 font-mono text-2xl font-bold tracking-wider text-brand-800">{{ $shipment->tracking_number }}</p>
        <div class="mt-4"><x-status-badge :status="$shipment->status" /></div>
        <div class="mt-6 flex flex-wrap justify-center gap-3">
            <a href="{{ route('track.show', $shipment->tracking_number) }}" class="btn btn-primary">Track shipment</a>
            @if($pending)<a href="{{ route('checkout.show', $shipment) }}" class="btn btn-accent">Pay now</a>@endif
            @auth<a href="{{ route('portal') }}" class="btn btn-secondary">Go to my account</a>@endauth
            <a href="{{ route('book.start') }}" class="btn btn-ghost">Send another</a>
        </div>
    </div>
    <div class="card card-body mt-6 text-sm">
        <h2 class="text-base">What happens next</h2>
        <ol class="mt-3 list-decimal space-y-2 pl-5 text-ink-700">
            @if($shipment->pickup_requested)<li>We'll schedule a rider to collect from {{ $shipment->pickup_city }}@if($shipment->pickup_date) on {{ $shipment->pickup_date->format('D j M') }}@endif.</li>@else<li>Drop the parcel at any <a href="{{ route('branches') }}">branch</a> and quote your tracking number.</li>@endif
            <li>You'll get updates by email{{ $shipment->user?->sms_consent_at ? ' and SMS' : '' }} as the parcel moves.</li>
            <li>The recipient will be contacted before delivery.</li>
        </ol>
        @if($shipment->payment_method === 'cod')<p class="mt-3 rounded bg-ink-50 p-3">Cash on delivery: the recipient pays <x-money :amount="$shipment->cod_amount" :currency="$shipment->currency" /> to the rider.</p>@endif
    </div>
</div>
@endsection
