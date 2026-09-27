@extends('layouts.portal', ['portal' => 'account'])
@section('title', 'Dashboard')
@section('content')
<x-dash-hero image="courier-boxes" eyebrow="Your deliveries" :title="'Hello, '.strtok(auth()->user()->name, ' ').' 👋'" from="You" to="Them">
    <a href="{{ route('book.start') }}" class="btn btn-accent btn-lg btn-shine"><x-icon name="plus" class="size-4" />Send a parcel</a>
    <a href="{{ route('track.form') }}" class="btn btn-on-dark btn-lg"><x-icon name="search" class="size-4" />Track</a>
</x-dash-hero>
@if(! auth()->user()->hasVerifiedEmail())
    <x-alert type="warning" class="mb-6">Please verify your email address. <form method="post" action="{{ route('verification.send') }}" class="inline">@csrf<button class="font-semibold underline" type="submit">Resend link</button></form></x-alert>
@endif
<div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <x-stat label="Active" :value="$counts['active']" icon="truck" :href="route('account.shipments.index')" />
    <x-stat label="Awaiting payment" :value="$counts['awaiting_payment']" icon="credit-card" :href="route('account.shipments.index', ['status' => 'pending_payment'])" />
    <x-stat label="Delivered" :value="$counts['delivered']" icon="check-circle" :href="route('account.shipments.index', ['status' => 'delivered'])" />
    <x-stat label="All shipments" :value="$counts['total']" icon="package" :href="route('account.shipments.index')" />
</div>
<div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
    <a href="{{ route('book.start') }}" class="quick-tile" data-reveal><x-illus name="box" size="size-12" />Send a parcel</a>
    <a href="{{ route('track.form') }}" class="quick-tile" data-reveal style="--d: 60ms"><x-illus name="track" tone="sky" size="size-12" />Track a shipment</a>
    <a href="{{ route('account.addresses.index') }}" class="quick-tile" data-reveal style="--d: 120ms"><x-illus name="pin" tone="accent" size="size-12" />Saved addresses</a>
    <a href="{{ route('account.support.index') }}" class="quick-tile" data-reveal style="--d: 180ms"><x-illus name="support" tone="violet" size="size-12" />Get help</a>
</div>
<div class="card mt-6">
    <div class="card-header"><h2 class="text-lg">Recent shipments</h2><a href="{{ route('account.shipments.index') }}" class="text-sm">View all</a></div>
    @if($recent->isEmpty())
        <x-empty icon="package" title="No shipments yet" action="Send your first parcel" :action-url="route('book.start')">Book a pickup in a couple of minutes and track it here.</x-empty>
    @else
        @include('shared.shipment-table', ['shipments' => $recent, 'showRoute' => 'account.shipments.show'])
    @endif
</div>
@endsection
