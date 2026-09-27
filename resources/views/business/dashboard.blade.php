@extends('layouts.portal', ['portal' => 'business'])
@section('title', 'Company dashboard')
@section('content')
<x-dash-hero image="warehouse-aisle" :eyebrow="\App\Models\BusinessMember::ROLE_LABELS[$membership->role]" :title="$business->name" from="Warehouse" to="Customer">
    @if($membership->can('create_shipments'))
        <a href="{{ route('book.start', ['as' => 'business']) }}" class="btn btn-accent btn-lg btn-shine"><x-icon name="plus" class="size-4" />New shipment</a>
        <a href="{{ route('business.bulk.create') }}" class="btn btn-on-dark btn-lg"><x-icon name="upload" class="size-4" />Bulk upload</a>
    @endif
</x-dash-hero>
<div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <x-stat label="Awaiting payment" :value="$stats['awaiting_payment']" icon="credit-card" :href="route('business.shipments.index', ['status' => 'pending_payment'])" />
    <x-stat label="In progress" :value="$stats['in_progress']" icon="truck" :href="route('business.shipments.index')" />
    <x-stat label="Needs attention" :value="$stats['exceptions']" icon="alert" :href="route('business.shipments.index', ['status' => 'delivery_attempted'])" hint="Failed attempts, holds, returns" />
    <x-stat label="Delivered (30 days)" :value="$stats['delivered_30d']" icon="check-circle" :href="route('business.shipments.index', ['status' => 'delivered'])" />
</div>
<div class="mt-2 text-xs text-ink-500">Payment terms: {{ $business->billsByInvoice() ? 'monthly invoice (due in '.$business->invoice_due_days.' days)' : 'pay per shipment' }}</div>
<div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-5">
    @if($membership->can('create_shipments'))<a href="{{ route('business.bulk.create') }}" class="quick-tile" data-reveal><x-illus name="upload" size="size-12" />Bulk upload</a>@endif
    <a href="{{ route('business.shipments.index') }}" class="quick-tile" data-reveal style="--d: 60ms"><x-illus name="track" tone="sky" size="size-12" />All shipments</a>
    @if($membership->can('view_invoices'))<a href="{{ route('business.invoices.index') }}" class="quick-tile" data-reveal style="--d: 120ms"><x-illus name="invoice" tone="violet" size="size-12" />Invoices</a>@endif
    @if($membership->can('view_reports'))<a href="{{ route('business.reports') }}" class="quick-tile" data-reveal style="--d: 180ms"><x-illus name="chart" tone="success" size="size-12" />Reports</a>@endif
    @if($membership->can('manage_team'))<a href="{{ route('business.team.index') }}" class="quick-tile" data-reveal style="--d: 240ms"><x-illus name="team" tone="accent" size="size-12" />Team</a>@endif
</div>
<div class="card mt-6">
    <div class="card-header"><h2 class="text-lg">Recent shipments</h2><a href="{{ route('business.shipments.index') }}" class="text-sm">View all</a></div>
    @if($recent->isEmpty())
        <x-empty icon="package" title="No shipments yet" :action="$membership->can('create_shipments') ? 'Create a shipment' : null" :action-url="route('book.start', ['as' => 'business'])">Create one shipment or upload a CSV of many.</x-empty>
    @else
        @include('shared.shipment-table', ['shipments' => $recent, 'showRoute' => 'business.shipments.show'])
    @endif
</div>
@endsection
