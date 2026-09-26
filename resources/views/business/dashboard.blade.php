@extends('layouts.portal', ['portal' => 'business'])
@section('title', 'Company dashboard')
@section('content')
<section class="photo-hero mb-6 rounded-2xl shadow-lg" data-reveal>
    <img src="{{ asset('images/warehouse-aisle-sm.webp') }}" alt="">
    <div class="flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8">
        <div>
            <p class="text-sm text-brand-200">{{ \App\Models\BusinessMember::ROLE_LABELS[$membership->role] }}</p>
            <h1 class="text-2xl text-white sm:text-3xl">{{ $business->name }}</h1>
        </div>
        <div class="flex flex-wrap gap-2">    @if($membership->can('create_shipments'))
        <a href="{{ route('business.bulk.create') }}" class="btn btn-on-dark"><x-icon name="upload" class="size-4" />Bulk upload</a>
        <a href="{{ route('book.start', ['as' => 'business']) }}" class="btn btn-accent"><x-icon name="plus" class="size-4" />New shipment</a>
    @endif</div>
    </div>
</section>
<div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <x-stat label="Awaiting payment" :value="$stats['awaiting_payment']" icon="credit-card" :href="route('business.shipments.index', ['status' => 'pending_payment'])" />
    <x-stat label="In progress" :value="$stats['in_progress']" icon="truck" :href="route('business.shipments.index')" />
    <x-stat label="Needs attention" :value="$stats['exceptions']" icon="alert" :href="route('business.shipments.index', ['status' => 'delivery_attempted'])" hint="Failed attempts, holds, returns" />
    <x-stat label="Delivered (30 days)" :value="$stats['delivered_30d']" icon="check-circle" :href="route('business.shipments.index', ['status' => 'delivered'])" />
</div>
<div class="mt-2 text-xs text-ink-500">Payment terms: {{ $business->billsByInvoice() ? 'monthly invoice (due in '.$business->invoice_due_days.' days)' : 'pay per shipment' }}</div>
<div class="card mt-6">
    <div class="card-header"><h2 class="text-lg">Recent shipments</h2><a href="{{ route('business.shipments.index') }}" class="text-sm">View all</a></div>
    @if($recent->isEmpty())
        <x-empty icon="package" title="No shipments yet" :action="$membership->can('create_shipments') ? 'Create a shipment' : null" :action-url="route('book.start', ['as' => 'business'])">Create one shipment or upload a CSV of many.</x-empty>
    @else
        @include('shared.shipment-table', ['shipments' => $recent, 'showRoute' => 'business.shipments.show'])
    @endif
</div>
@endsection
