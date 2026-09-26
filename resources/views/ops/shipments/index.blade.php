@extends('layouts.portal', ['portal' => 'ops'])
@section('title', 'Shipments')
@section('content')
@php $tz = \App\Support\Settings::get('timezone'); @endphp
<x-page-header title="Shipments"><a href="{{ route('ops.shipments.export', request()->query()) }}" class="btn btn-secondary"><x-icon name="download" class="size-4" />Export CSV</a></x-page-header>
<form method="get" class="card card-body mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 xl:items-end" role="search">
    <x-field name="q" label="Search" :value="request('q')" placeholder="Tracking, name, phone, email" class="lg:col-span-2" />
    <x-field name="status" label="Status" type="select" :options="\App\Enums\ShipmentStatus::options()" :value="request('status')" placeholder="Any" />
    <x-field name="service_id" label="Service" type="select" :options="$services" :value="request('service_id')" placeholder="Any" />
    <x-field name="destination_zone_id" label="Destination" type="select" :options="$zones" :value="request('destination_zone_id')" placeholder="Any" />
    <x-field name="from" label="Created from" type="date" :value="request('from')" />
    <x-field name="to" label="to" type="date" :value="request('to')" />
    <div class="flex gap-2 sm:col-span-2 lg:col-span-4 xl:col-span-7"><button class="btn btn-secondary" type="submit">Apply</button>@if(request()->query())<a href="{{ route('ops.shipments.index') }}" class="btn btn-ghost">Clear</a>@endif</div>
</form>
<div class="card">
    @if($shipments->isEmpty())<x-empty icon="search" title="No shipments match these filters" />@else
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Tracking</th><th>Customer</th><th>Route</th><th><x-sort-link column="status" label="Status" /></th><th>Rider</th><th class="text-right"><x-sort-link column="total" label="Total" /></th><th><x-sort-link column="created_at" label="Created" /></th></tr></thead>
        <tbody>@foreach($shipments as $s)<tr>
            <td><a href="{{ route('ops.shipments.show', $s) }}" class="font-mono font-semibold">{{ $s->tracking_number }}</a></td>
            <td>{{ $s->business?->name ?? $s->user?->name ?? $s->sender_name }}@if(!$s->user_id)<span class="badge badge-neutral ml-1">Guest</span>@endif</td>
            <td class="whitespace-nowrap">{{ $s->pickup_city }} → {{ $s->delivery_city }}<div class="text-xs text-ink-500">{{ $s->service->name }}</div></td>
            <td><x-status-badge :status="$s->status" /><div class="text-xs text-ink-500">{{ $s->status_changed_at?->diffForHumans() }}</div></td>
            <td class="text-sm">{{ $s->activeAssignment?->rider?->name ?? '—' }}</td>
            <td class="text-right whitespace-nowrap"><x-money :amount="$s->total" :currency="$s->currency" /><div class="text-xs uppercase text-ink-500">{{ $s->payment_method }}</div></td>
            <td class="whitespace-nowrap">{{ $s->created_at->timezone($tz)->format('j M, H:i') }}</td>
        </tr>@endforeach</tbody>
    </table></div><div class="border-t border-ink-200 p-4">{{ $shipments->links() }}</div>@endif
</div>
@endsection
