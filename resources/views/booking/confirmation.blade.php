@extends('layouts.public')
@section('title', 'Booking confirmed')
@section('content')
@php $pending = $shipment->status->value === 'pending_payment'; @endphp
<div class="mx-auto max-w-2xl">
    @include('booking._steps', ['current' => $pending ? 3 : 4])
    <div class="card card-body text-center">
        <span class="mx-auto grid size-16 place-items-center rounded-full {{ $pending ? 'bg-warning-50 text-warning-800' : 'bg-success-50 text-success-700' }}"><x-icon :name="$pending ? 'clock' : 'check-circle'" class="size-8" /></span>
        <h1 class="mt-4 text-2xl">{{ $pending ? 'Awaiting payment' : 'Your shipment is booked' }}</h1>
        <p class="mt-2 text-ink-600">Keep this tracking number — we've also emailed it to {{ $shipment->sender_email ?? 'you' }}.</p>
        <p class="mx-auto mt-5 w-fit rounded-xl border-2 border-dashed border-brand-300 bg-brand-50 px-6 py-3 font-mono text-2xl font-bold tracking-wider text-brand-800">{{ $shipment->tracking_number }}</p>
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
