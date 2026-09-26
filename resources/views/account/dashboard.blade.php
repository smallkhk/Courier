@extends('layouts.portal', ['portal' => 'account'])
@section('title', 'Dashboard')
@section('content')
<section class="photo-hero mb-6 rounded-2xl shadow-lg" data-reveal>
    <img src="{{ asset('images/courier-boxes-sm.webp') }}" alt="">
    <div class="flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8">
        <div>
            <p class="text-sm text-brand-200">{{ now()->timezone(\App\Support\Settings::get('timezone'))->format('l j F') }}</p>
            <h1 class="text-2xl text-white sm:text-3xl">Hello, {{ strtok(auth()->user()->name, ' ') }}</h1>
        </div>
        <a href="{{ route('book.start') }}" class="btn btn-accent btn-lg"><x-icon name="plus" class="size-4" />Send a parcel</a>
    </div>
</section>
@if(! auth()->user()->hasVerifiedEmail())
    <x-alert type="warning" class="mb-6">Please verify your email address. <form method="post" action="{{ route('verification.send') }}" class="inline">@csrf<button class="font-semibold underline" type="submit">Resend link</button></form></x-alert>
@endif
<div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <x-stat label="Active" :value="$counts['active']" icon="truck" :href="route('account.shipments.index')" />
    <x-stat label="Awaiting payment" :value="$counts['awaiting_payment']" icon="credit-card" :href="route('account.shipments.index', ['status' => 'pending_payment'])" />
    <x-stat label="Delivered" :value="$counts['delivered']" icon="check-circle" :href="route('account.shipments.index', ['status' => 'delivered'])" />
    <x-stat label="All shipments" :value="$counts['total']" icon="package" :href="route('account.shipments.index')" />
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
