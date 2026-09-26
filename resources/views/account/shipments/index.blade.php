@extends('layouts.portal', ['portal' => 'account'])
@section('title', 'My shipments')
@section('content')
<x-page-header title="My shipments"><a href="{{ route('book.start') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" />New shipment</a></x-page-header>
<form method="get" class="mb-4 flex flex-wrap items-end gap-3" role="search">
    <div class="w-full sm:w-72"><x-field name="q" label="Search" :value="request('q')" placeholder="Tracking no., recipient, city" /></div>
    <div class="w-full sm:w-56"><x-field name="status" label="Status" type="select" :options="\App\Enums\ShipmentStatus::options()" :value="request('status')" placeholder="Any status" /></div>
    <button class="btn btn-secondary" type="submit">Filter</button>
    @if(request()->hasAny(['q', 'status']))<a href="{{ route('account.shipments.index') }}" class="btn btn-ghost">Clear</a>@endif
</form>
<div class="card">
    @if($shipments->isEmpty())
        <x-empty icon="search" title="No shipments found">{{ request()->hasAny(['q','status']) ? 'Try different filters.' : 'Your shipments will appear here.' }}</x-empty>
    @else
        @include('shared.shipment-table', ['shipments' => $shipments, 'showRoute' => 'account.shipments.show'])
    @endif
</div>
@endsection
