@extends('layouts.portal', ['portal' => 'account'])
@section('title', $shipment->tracking_number)
@section('content')
<x-page-header :title="$shipment->tracking_number" :back="route('account.shipments.index')">
    <x-status-badge :status="$shipment->status" class="self-center text-sm" />
    @if($shipment->isPayable())<a href="{{ route('checkout.show', $shipment) }}" class="btn btn-accent">Pay now</a>@endif
    <a href="{{ route('track.show', $shipment->tracking_number) }}" class="btn btn-secondary">Public tracking page</a>
</x-page-header>
@component('shared.shipment-detail', ['shipment' => $shipment])
    @foreach($shipment->payments as $p)
        <div class="card card-body text-sm">
            <div class="flex items-center justify-between"><h2 class="text-base">Payment</h2><x-pill :tone="$p->status === 'successful' ? 'success' : ($p->status === 'pending' ? 'warning' : 'neutral')">{{ ucfirst($p->status) }}</x-pill></div>
            <p class="mt-1 font-mono text-xs">{{ $p->reference }}</p>
            @if(in_array($p->status, ['successful', 'refunded']))<a href="{{ route('account.payments.show', $p) }}" class="btn btn-secondary btn-sm mt-3 w-full"><x-icon name="receipt" class="size-4" />View receipt</a>@endif
        </div>
    @endforeach
    <div class="card card-body text-sm">
        <h2 class="text-base">Need help?</h2>
        <a href="{{ route('account.support.create', ['tracking' => $shipment->tracking_number]) }}" class="btn btn-secondary mt-3 w-full"><x-icon name="life-buoy" class="size-4" />Report an issue</a>
        @if($canCancel)
            <form method="post" action="{{ route('account.shipments.cancel', $shipment) }}" class="mt-3" x-data @submit="if(!confirm('Cancel this shipment?')) $event.preventDefault()">
                @csrf
                <x-field name="reason" label="Reason (optional)" maxlength="200" />
                <button class="btn btn-danger mt-2 w-full" type="submit">Cancel shipment</button>
            </form>
        @endif
    </div>
@endcomponent
@endsection
