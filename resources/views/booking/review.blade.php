@extends('layouts.public')
@section('title', 'Review your shipment')
@section('content')
<div class="mx-auto max-w-5xl">
    @include('booking._steps', ['current' => 2])
    <x-page-header title="Review & confirm" :back="route('book.start')" />
    @if($priceChanged)<x-alert type="warning" class="mb-6" title="The price has been updated">Your previous quote expired, so we recalculated it with current rates. Please check the new total.</x-alert>@endif
    <div class="grid gap-6 lg:grid-cols-[1fr_380px]">
        <div class="space-y-4">
            <div class="card card-body grid gap-6 sm:grid-cols-2">
                <div>
                    <h2 class="flex items-center gap-2 text-sm font-semibold tracking-wide text-ink-500 uppercase"><x-icon name="map-pin" class="size-4" />Pickup</h2>
                    <p class="mt-2 font-medium">{{ $d['sender_name'] }}</p>
                    <p class="text-sm text-ink-700">{{ $d['pickup_address'] }}, {{ $d['pickup_city'] }}, {{ $d['pickup_state'] }}</p>
                    <p class="text-sm text-ink-600">{{ $d['sender_phone'] }} · {{ $d['sender_email'] }}</p>
                    <p class="mt-2 text-sm">@if($d['pickup_requested'])Collection {{ !empty($d['pickup_date']) ? 'on '.\Illuminate\Support\Carbon::parse($d['pickup_date'])->format('D j M') : '' }} {{ \App\Models\Shipment::PICKUP_WINDOWS[$d['pickup_window'] ?? ''] ?? '' }}@else Drop-off at a branch @endif</p>
                </div>
                <div>
                    <h2 class="flex items-center gap-2 text-sm font-semibold tracking-wide text-ink-500 uppercase"><x-icon name="navigation" class="size-4" />Delivery</h2>
                    <p class="mt-2 font-medium">{{ $d['recipient_name'] }}</p>
                    <p class="text-sm text-ink-700">{{ $d['delivery_address'] }}, {{ $d['delivery_city'] }}, {{ $d['delivery_state'] }}</p>
                    <p class="text-sm text-ink-600">{{ $d['recipient_phone'] }}</p>
                </div>
            </div>
            <div class="card card-body">
                <h2 class="text-sm font-semibold tracking-wide text-ink-500 uppercase">Package</h2>
                <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-3">
                    <div><dt class="text-ink-500">Contents</dt><dd>{{ $d['package_description'] }} ({{ \App\Models\Shipment::CATEGORIES[$d['package_category']] }})</dd></div>
                    <div><dt class="text-ink-500">Parcels</dt><dd>{{ count($d['parcels']) }}</dd></div>
                    <div><dt class="text-ink-500">Service</dt><dd>{{ $quote->service->name }}</dd></div>
                    @if(!empty($d['special_handling']))<div class="sm:col-span-3"><dt class="text-ink-500">Handling</dt><dd>{{ collect($d['special_handling'])->map(fn($h) => \App\Models\Shipment::HANDLING[$h])->join(', ') }}</dd></div>@endif
                </dl>
                <p class="mt-4 text-sm text-ink-600">
                    @if($quote->service->transitLabel())Estimated delivery: {{ $quote->service->transitLabel() }} from pickup. Estimates are not guaranteed.@else No delivery-time estimate is available for this service.@endif
                </p>
            </div>
        </div>

        <form method="post" action="{{ route('book.confirm') }}" class="card card-body h-fit space-y-4 lg:sticky lg:top-24">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ $idempotency }}">
            <h2 class="text-lg">Price</h2>
            <x-price-breakdown :breakdown="$quote->breakdown" :currency="$quote->currency" />
            <p class="text-xs text-ink-500">Quote valid until {{ $quote->expires_at->timezone(\App\Support\Settings::get('timezone'))->format('g:i a') }}.</p>

            <fieldset>
                <legend class="label">Payment</legend>
                @if($business && $business->isApproved() && $business->billsByInvoice())
                    <input type="hidden" name="payment_method" value="invoice">
                    <p class="rounded-lg bg-ink-50 p-3 text-sm">Billed to <strong>{{ $business->name }}</strong> on your monthly invoice.</p>
                @else
                    <div class="space-y-2">
                        <label class="flex items-start gap-3 rounded-lg border border-ink-200 p-3 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                            <input type="radio" name="payment_method" value="online" class="mt-1 accent-brand-600" checked> <span><span class="font-medium">Pay online now</span><br><span class="text-xs text-ink-600">Card, bank transfer or USSD via our payment provider.</span></span>
                        </label>
                        @if($codEnabled)
                            <label class="flex items-start gap-3 rounded-lg border border-ink-200 p-3 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                                <input type="radio" name="payment_method" value="cod" class="mt-1 accent-brand-600"> <span><span class="font-medium">Cash on delivery</span><br><span class="text-xs text-ink-600">The recipient pays the delivery charge in cash to the rider.</span></span>
                            </label>
                        @endif
                    </div>
                @endif
            </fieldset>

            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" name="accept_terms" value="1" class="checkbox mt-0.5" required>
                <span>I confirm the details are correct, the parcel contains no prohibited items, and I accept the <a href="{{ route('legal', 'terms') }}" target="_blank">terms</a> and <a href="{{ route('legal', 'delivery-policy') }}" target="_blank">delivery & claims policy</a>.</span>
            </label>
            @error('accept_terms')<p class="error-text">{{ $message }}</p>@enderror
            <button class="btn btn-primary btn-lg w-full" type="submit">Confirm booking</button>
            <a href="{{ route('book.start') }}" class="btn btn-ghost w-full">Edit details</a>
        </form>
    </div>
</div>
@endsection
