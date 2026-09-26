@extends('layouts.public')
@section('title', 'Pricing & quote')
@section('hero')
<x-photo-hero image="loading-parcels" eyebrow="Instant quote" icon="tag" title="Know the price before you book" subtitle="Calculated from our current rates, with a full breakdown. The final price is confirmed when you book with full details." />
@endsection
@section('content')
<div class="grid gap-8 lg:grid-cols-[1fr_380px]">
    <form method="post" action="{{ route('pricing.estimate') }}" class="card card-body space-y-5" data-reveal>
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="origin_zone_id" label="From (pickup area)" type="select" required :options="$zones->mapWithKeys(fn($z) => [$z->id => $z->name.' — '.$z->state])" />
            <x-field name="destination_zone_id" label="To (delivery area)" type="select" required :options="$zones->mapWithKeys(fn($z) => [$z->id => $z->name.' — '.$z->state])" />
        </div>
        <x-field name="service_id" label="Service" type="select" required :options="$services->pluck('name', 'id')" />
        <fieldset>
            <legend class="label">Parcel size</legend>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                <x-field name="weight_kg" label="Weight (kg)" type="number" step="0.01" min="0.01" required />
                <x-field name="length_cm" label="Length (cm)" type="number" step="0.1" min="1" />
                <x-field name="width_cm" label="Width (cm)" type="number" step="0.1" min="1" />
                <x-field name="height_cm" label="Height (cm)" type="number" step="0.1" min="1" />
                <x-field name="parcel_count" label="Parcels" type="number" min="1" max="20" value="1" required />
            </div>
            <p class="hint">Dimensions are optional; large light parcels are charged by volumetric weight.</p>
        </fieldset>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="declared_value" label="Declared value (optional)" type="number" step="0.01" min="0" />
            <div class="space-y-3 pt-7">
                <label class="flex items-center gap-2 text-sm"><input type="hidden" name="insured" value="0"><input type="checkbox" class="checkbox" name="insured" value="1" @checked(old('insured'))> Add insurance</label>
                <label class="flex items-center gap-2 text-sm"><input type="hidden" name="pickup_requested" value="0"><input type="checkbox" class="checkbox" name="pickup_requested" value="1" @checked(old('pickup_requested', true))> Pick up from my address</label>
            </div>
        </div>
        <button class="btn btn-primary btn-lg w-full sm:w-auto" type="submit">Calculate price</button>
    </form>

    <aside class="space-y-4" aria-live="polite">
        @isset($quote)
            <div class="card card-body">
                <p class="text-sm text-ink-600">{{ $quote->service->name }}</p>
                <p class="mt-1 text-3xl font-bold"><x-money :amount="$quote->total" :currency="$quote->currency" /></p>
                @if($quote->service->transitLabel())<p class="mt-1 text-sm text-ink-600">{{ $quote->service->transitLabel() }}</p>@endif
                <div class="mt-4"><x-price-breakdown :breakdown="$quote->breakdown" :currency="$quote->currency" /></div>
                <p class="mt-3 text-xs text-ink-500">Valid until {{ $quote->expires_at->timezone(\App\Support\Settings::get('timezone'))->format('g:i a') }}. Changing details at booking may change the price.</p>
                <a href="{{ route('book.start') }}" class="btn btn-primary mt-4 w-full">Book this shipment</a>
            </div>
        @else
            <div class="card card-body text-sm text-ink-600">
                <x-illus name="wallet" tone="accent" size="size-12" />
                <h2 class="mt-3 text-base">What affects the price?</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li>Pickup and delivery areas</li>
                    <li>Weight or volumetric weight</li>
                    <li>Number of parcels and speed of service</li>
                    <li>Pickup, remote-area surcharges and optional insurance</li>
                    <li>Applicable taxes</li>
                </ul>
            </div>
        @endisset
    </aside>
</div>
@endsection
