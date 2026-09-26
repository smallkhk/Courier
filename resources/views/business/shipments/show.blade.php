@extends('layouts.portal', ['portal' => 'business'])
@section('title', $shipment->tracking_number)
@section('content')
<x-page-header :title="$shipment->tracking_number" :back="route('business.shipments.index')">
    <x-status-badge :status="$shipment->status" class="self-center text-sm" />
    @if($shipment->isPayable())<a href="{{ route('checkout.show', $shipment) }}" class="btn btn-accent">Pay now</a>@endif
</x-page-header>
@component('shared.shipment-detail', ['shipment' => $shipment])
    <div class="card card-body text-sm">
        <h2 class="text-base">Need help?</h2>
        <a href="{{ route('support.contact', ['tracking' => $shipment->tracking_number]) }}" class="btn btn-secondary mt-3 w-full"><x-icon name="life-buoy" class="size-4" />Report an issue</a>
    </div>
@endcomponent
@endsection
