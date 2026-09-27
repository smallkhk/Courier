@extends('layouts.public')
@section('title', 'Pricing & quote')
@section('hero')
<x-photo-hero image="loading-parcels" eyebrow="Instant quote" icon="tag" title="Know the price before you book" subtitle="Calculated from our current rates, with a full breakdown. The final price is confirmed when you book with full details." />
@endsection
@section('content')
<div class="grid gap-8 lg:grid-cols-[1fr_360px]">
    <form method="post" action="{{ route('pricing.estimate') }}" class="card card-body space-y-5" data-reveal>
        @csrf
        <div class="grid gap-6 lg:grid-cols-2">
            <div>
                <h2 class="mb-3 flex items-center gap-2 text-base"><x-icon name="map-pin" class="size-4 text-brand-600" />From</h2>
                <x-address-picker prefix="from" legend="Ship from" :compact="true" />
            </div>
            <div>
                <h2 class="mb-3 flex items-center gap-2 text-base"><x-icon name="navigation" class="size-4 text-brand-600" />To</h2>
                <x-address-picker prefix="to" legend="Ship to" :compact="true" />
            </div>
        </div>
        <x-field name="service_id" label="Service" type="select" required :options="$services->pluck('name', 'id')" />
        <fieldset x-data="{ units: '{{ old('units', \App\Support\Units::system()) }}' }">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <legend class="label">Parcel size</legend>
                <div class="inline-flex rounded-lg border border-ink-300 p-0.5 text-sm" role="radiogroup" aria-label="Units">
                    <label class="cursor-pointer rounded-md px-3 py-1" :class="units === 'imperial' ? 'bg-brand-600 text-white' : 'text-ink-700'"><input type="radio" name="units" value="imperial" x-model="units" class="sr-only">lb / in</label>
                    <label class="cursor-pointer rounded-md px-3 py-1" :class="units === 'metric' ? 'bg-brand-600 text-white' : 'text-ink-700'"><input type="radio" name="units" value="metric" x-model="units" class="sr-only">kg / cm</label>
                </div>
            </div>
            <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-5">
                <x-field name="weight" label="Weight" type="number" step="0.01" min="0.01" required />
                <x-field name="length" label="Length" type="number" step="0.1" min="0.1" />
                <x-field name="width" label="Width" type="number" step="0.1" min="0.1" />
                <x-field name="height" label="Height" type="number" step="0.1" min="0.1" />
                <x-field name="parcel_count" label="Parcels" type="number" min="1" max="20" value="1" required />
            </div>
            <p class="hint">Weight in <span x-text="units === 'imperial' ? 'pounds' : 'kilograms'"></span>, dimensions in <span x-text="units === 'imperial' ? 'inches' : 'centimeters'"></span>. Large, light parcels are billed by dimensional weight.</p>
        </fieldset>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="declared_value" :label="'Value of contents ('.\App\Support\Settings::currency().', optional)'" type="number" step="0.01" min="0" />
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
                <p class="text-sm text-ink-600">{{ $quote->service->name }}@isset($route) · {{ $route[0]->name }} → {{ $route[1]->name }}@endisset</p>
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
                    <li>Where it's going — local, domestic or international</li>
                    <li>Weight or dimensional weight</li>
                    <li>Number of parcels and speed of service</li>
                    <li>Pickup, remote-area surcharges and optional insurance</li>
                    <li>Applicable taxes</li>
                </ul>
            </div>
        @endisset
    </aside>
</div>
@endsection
