@extends('layouts.portal', ['portal' => 'business'])
@section('title', 'Shipments')
@section('content')
@php $tz = \App\Support\Settings::get('timezone'); @endphp
<x-page-header title="Shipments">
    <a href="{{ route('business.shipments.export', request()->query()) }}" class="btn btn-secondary"><x-icon name="download" class="size-4" />Export CSV</a>
    @if($membership->can('create_shipments'))<a href="{{ route('book.start', ['as' => 'business']) }}" class="btn btn-primary"><x-icon name="plus" class="size-4" />New</a>@endif
</x-page-header>
<form method="get" class="card card-body mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5 lg:items-end" role="search">
    <x-field name="q" label="Search" :value="request('q')" placeholder="Tracking, recipient, phone, ref" class="lg:col-span-2" />
    <x-field name="status" label="Status" type="select" :options="\App\Enums\ShipmentStatus::options()" :value="request('status')" placeholder="Any" />
    <x-field name="from" label="From" type="date" :value="request('from')" />
    <x-field name="to" label="To" type="date" :value="request('to')" />
    <div class="flex gap-2 lg:col-span-5"><button class="btn btn-secondary" type="submit">Apply filters</button>@if(request()->query())<a class="btn btn-ghost" href="{{ route('business.shipments.index') }}">Clear</a>@endif</div>
</form>
<form method="post" action="{{ route('business.shipments.pay') }}" class="card" x-data="{ selected: [] }">
    @csrf
    @if($shipments->isEmpty())
        <x-empty icon="search" title="No shipments match" />
    @else
    <div class="card-header" x-show="selected.length" x-cloak>
        <p class="text-sm"><span x-text="selected.length"></span> selected for payment</p>
        <button class="btn btn-accent btn-sm" type="submit">Pay selected</button>
    </div>
    <div class="table-wrap"><table class="table">
        <thead><tr>
            <th class="w-8"><span class="sr-only">Select</span></th>
            <th>Tracking</th><th>Recipient</th><th><x-sort-link column="status" label="Status" /></th>
            <th class="text-right"><x-sort-link column="total" label="Total" /></th><th><x-sort-link column="created_at" label="Created" /></th>
        </tr></thead>
        <tbody>
        @foreach($shipments as $s)
            <tr>
                <td>@if($s->isPayable() && $membership->can('create_shipments'))<input type="checkbox" class="checkbox" name="shipments[]" value="{{ $s->tracking_number }}" x-model="selected" aria-label="Select {{ $s->tracking_number }} for payment">@endif</td>
                <td><a href="{{ route('business.shipments.show', $s) }}" class="font-mono font-semibold">{{ $s->tracking_number }}</a></td>
                <td>{{ $s->recipient_name }}<div class="text-xs text-ink-500">{{ \App\Support\Countries::flag($s->delivery_country) }} {{ $s->area('delivery') }}</div></td>
                <td><x-status-badge :status="$s->status" /></td>
                <td class="text-right"><x-money :amount="$s->total" :currency="$s->currency" /></td>
                <td class="text-ink-600">{{ $s->created_at->timezone($tz)->format('j M Y') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    <div class="border-t border-ink-200 p-4">{{ $shipments->links() }}</div>
    @endif
</form>
@endsection
