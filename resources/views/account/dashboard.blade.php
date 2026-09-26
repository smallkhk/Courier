@extends('layouts.portal', ['portal' => 'account'])
@section('title', 'Dashboard')
@section('content')
<x-page-header :title="'Hello, '.strtok(auth()->user()->name, ' ')" subtitle="Here's what's happening with your parcels.">
    <a href="{{ route('book.start') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" />Send a parcel</a>
</x-page-header>
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
