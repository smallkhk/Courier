@extends('layouts.portal', ['portal' => $portal])
@section('title', 'Saved addresses')
@section('content')
<x-page-header :title="$portal === 'business' ? 'Pickup addresses' : 'Saved addresses'" subtitle="Reuse these when booking." />
<div class="grid gap-6 lg:grid-cols-[1fr_480px]">
    <div class="space-y-3">
        @forelse($addresses as $a)
            <div class="card card-body flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="flex items-center gap-2 font-semibold"><x-icon name="map-pin" class="size-4 text-brand-600" />{{ $a->label }}</p>
                    <p class="mt-1 text-sm text-ink-700">{{ $a->contact_name }} · {{ \App\Support\Phone::display($a->phone) }}</p>
                    <p class="text-sm text-ink-600">{{ \App\Support\Countries::flag($a->country_code) }} {{ $a->oneLine() }}</p>
                </div>
                <form method="post" action="{{ route($portal === 'business' ? 'business.addresses.destroy' : 'account.addresses.destroy', $a) }}" x-data @submit="if(!confirm('Remove this address?')) $event.preventDefault()">
                    @csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-danger-700" type="submit">Remove</button>
                </form>
            </div>
        @empty
            <div class="card"><x-empty icon="map-pin" title="No saved addresses">Add one to speed up booking.</x-empty></div>
        @endforelse
    </div>
    <form method="post" action="{{ $action }}" class="card card-body h-fit space-y-4">
        @csrf
        <h2 class="text-lg">Add an address</h2>
        <x-field name="label" label="Label" required placeholder="Home, Office, Warehouse…" />
        <x-field name="contact_name" label="Contact name" required />
        <x-field name="phone" label="Phone" type="tel" required />
        <x-field name="email" label="Email (optional)" type="email" />
        <x-address-picker prefix="a" legend="Address" />
        <x-field name="landmark" label="Landmark (optional)" />
        <button class="btn btn-primary w-full" type="submit">Save address</button>
    </form>
</div>
@endsection
