@extends('layouts.public')
@section('title', 'Send a parcel')
@section('content')
@php
    $v = fn($k, $d = null) => old($k, $details[$k] ?? $d);
    $parcelRows = old('parcels', $details['parcels'] ?? []);
    $units = $v('units', \App\Support\Units::system());
    $addr = fn ($side) => collect(['address', 'address2', 'city', 'region', 'postal_code', 'country', 'lat', 'lng', 'place_id'])->mapWithKeys(fn ($k) => [$k => $details[$side.'_'.$k] ?? null])->all();
@endphp
<div class="mx-auto max-w-4xl">
    @include('booking._steps', ['current' => 1])
    <x-page-header title="Send a parcel" subtitle="Anywhere we deliver — across the street, across the country or across borders. You'll see the full price before you pay." />
    @if($membership && $membership->business->isApproved() && $membership->can('create_shipments'))
        <div class="alert alert-info mb-6">
            <x-icon name="building" />
            <div>Booking as <strong>{{ $business ? $business->name : 'yourself (personal)' }}</strong>.
                <a href="{{ route('book.start', ['as' => $business ? 'personal' : 'business']) }}">Switch to {{ $business ? 'personal' : $membership->business->name }}</a></div>
        </div>
    @endif
    @guest
        <div class="alert alert-info mb-6"><x-icon name="info" /><div>You can book as a guest. <a href="{{ route('login') }}">Sign in</a> to save addresses and see all your shipments in one place.</div></div>
    @endguest

    <form method="post" action="{{ route('book.quote') }}" class="space-y-6" novalidate
          x-data="{ pc: '{{ $v('pickup_country', \App\Support\Settings::get('default_country')) }}', dc: '{{ $v('delivery_country', \App\Support\Settings::get('default_country')) }}' }"
          @country-change="$event.detail.prefix === 'pickup' ? pc = $event.detail.country : dc = $event.detail.country">
        @csrf
        <section class="card" aria-labelledby="pickup-h" data-reveal>
            <div class="card-header"><h2 id="pickup-h" class="flex items-center gap-2 text-lg"><span class="grid size-7 place-items-center rounded-full bg-brand-600 text-sm text-white">1</span>Pickup — sender</h2></div>
            <div class="card-body space-y-5">
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-field name="sender_name" label="Sender name" required autocomplete="name" :value="$v('sender_name')" />
                    <x-field name="sender_phone" label="Sender phone" type="tel" required autocomplete="tel" :value="$v('sender_phone')" placeholder="+1 212 555 0123" />
                    <x-field name="sender_email" label="Sender email" type="email" required autocomplete="email" :value="$v('sender_email')" />
                </div>
                <x-address-picker prefix="pickup" legend="Pickup address" :values="$addr('pickup')" :saved="$addresses->all()" />
                <x-field name="pickup_instructions" label="Pickup instructions (optional)" type="textarea" rows="2" :value="$v('pickup_instructions')" placeholder="Gate code, loading dock, best time…" />
                <div class="rounded-lg bg-ink-50 p-4" x-data="{ pickup: {{ $v('pickup_requested', '1') ? 'true' : 'false' }} }">
                    <label class="flex items-center gap-2 font-medium"><input type="hidden" name="pickup_requested" value="0"><input type="checkbox" class="checkbox" name="pickup_requested" value="1" x-model="pickup"> Collect from this address</label>
                    <p class="hint">Untick to drop the parcel off at one of our locations instead.</p>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2" x-show="pickup" x-cloak>
                        <x-field name="pickup_date" label="Preferred pickup date" type="date" :value="$v('pickup_date', now()->addWeekday()->toDateString())" min="{{ now()->toDateString() }}" max="{{ now()->addDays(29)->toDateString() }}" />
                        <x-field name="pickup_window" label="Time window" type="select" :options="\App\Models\Shipment::PICKUP_WINDOWS" :value="$v('pickup_window')" placeholder="Any time" />
                    </div>
                </div>
            </div>
        </section>

        <section class="card" aria-labelledby="deliv-h" data-reveal>
            <div class="card-header"><h2 id="deliv-h" class="flex items-center gap-2 text-lg"><span class="grid size-7 place-items-center rounded-full bg-brand-600 text-sm text-white">2</span>Delivery — recipient</h2>
                <span x-show="pc && dc && pc !== dc" x-cloak class="badge badge-accent"><x-icon name="globe" class="size-3.5" />International shipment</span></div>
            <div class="card-body space-y-5">
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-field name="recipient_name" label="Recipient name" required :value="$v('recipient_name')" />
                    <x-field name="recipient_phone" label="Recipient phone" type="tel" required :value="$v('recipient_phone')" hint="Include the country code if abroad." />
                    <x-field name="recipient_email" label="Recipient email (optional)" type="email" :value="$v('recipient_email')" />
                </div>
                <x-address-picker prefix="delivery" legend="Delivery address" :values="$addr('delivery')" :saved="$addresses->all()" />
                <x-field name="delivery_instructions" label="Delivery instructions (optional)" type="textarea" rows="2" :value="$v('delivery_instructions')" placeholder="Leave with doorman, buzzer #, safe place…" />
            </div>
        </section>

        <section class="card" aria-labelledby="pkg-h" data-reveal>
            <div class="card-header"><h2 id="pkg-h" class="flex items-center gap-2 text-lg"><span class="grid size-7 place-items-center rounded-full bg-brand-600 text-sm text-white">3</span>Package & service</h2></div>
            <div class="card-body space-y-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="package_description" label="What are you sending?" required :value="$v('package_description')" placeholder="e.g. 2 pairs of sneakers" />
                    <x-field name="package_category" label="Category" type="select" required :options="\App\Models\Shipment::CATEGORIES" :value="$v('package_category')" />
                </div>
                <fieldset x-data="parcels(@js(array_values($parcelRows)), @js($units))">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <legend class="label">Parcels</legend>
                        <div class="inline-flex rounded-lg border border-ink-300 p-0.5 text-sm" role="radiogroup" aria-label="Units">
                            <label class="cursor-pointer rounded-md px-3 py-1" :class="units === 'imperial' ? 'bg-brand-600 text-white' : 'text-ink-700'"><input type="radio" name="units" value="imperial" x-model="units" class="sr-only">lb / in</label>
                            <label class="cursor-pointer rounded-md px-3 py-1" :class="units === 'metric' ? 'bg-brand-600 text-white' : 'text-ink-700'"><input type="radio" name="units" value="metric" x-model="units" class="sr-only">kg / cm</label>
                        </div>
                    </div>
                    <div class="mt-2 space-y-3">
                        <template x-for="(row, i) in rows" :key="i">
                            <div class="grid grid-cols-2 items-end gap-3 rounded-lg border border-ink-200 p-3 sm:grid-cols-[repeat(4,1fr)_auto]">
                                <div><label class="label" :for="'w'+i">Weight (<span x-text="units === 'imperial' ? 'lb' : 'kg'"></span>) <span class="text-danger-700" aria-hidden="true">*</span></label><input class="input" type="number" step="0.01" min="0.01" required :id="'w'+i" :name="'parcels['+i+'][weight]'" x-model="row.weight"></div>
                                <div><label class="label" :for="'l'+i">Length (<span x-text="units === 'imperial' ? 'in' : 'cm'"></span>)</label><input class="input" type="number" step="0.1" min="0.1" :id="'l'+i" :name="'parcels['+i+'][length]'" x-model="row.length"></div>
                                <div><label class="label" :for="'wd'+i">Width (<span x-text="units === 'imperial' ? 'in' : 'cm'"></span>)</label><input class="input" type="number" step="0.1" min="0.1" :id="'wd'+i" :name="'parcels['+i+'][width]'" x-model="row.width"></div>
                                <div><label class="label" :for="'h'+i">Height (<span x-text="units === 'imperial' ? 'in' : 'cm'"></span>)</label><input class="input" type="number" step="0.1" min="0.1" :id="'h'+i" :name="'parcels['+i+'][height]'" x-model="row.height"></div>
                                <button type="button" class="btn btn-ghost col-span-2 sm:col-span-1" @click="remove(i)" x-show="rows.length > 1" :aria-label="'Remove parcel '+(i+1)"><x-icon name="x" class="size-4" /><span class="sm:sr-only">Remove</span></button>
                            </div>
                        </template>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm mt-3" @click="add()"><x-icon name="plus" class="size-4" />Add another parcel</button>
                    <p class="hint">Dimensions are optional; large, light parcels are billed by dimensional weight.</p>
                    @if($errors->has('parcels*'))<p class="error-text"><x-icon name="alert" class="size-4" />Check the weight and size of each parcel.</p>@endif
                </fieldset>
                <x-field name="service_id" label="Delivery service" type="select" required :options="$services->mapWithKeys(fn($s) => [$s->id => $s->name.($s->transitLabel() ? ' — '.$s->transitLabel() : '')])" :value="$v('service_id')" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="declared_value" :label="'Value of contents ('.\App\Support\Settings::currency().')'" type="number" step="0.01" min="0" :value="$v('declared_value')" hint="Used for insurance, claims and customs." />
                    <label class="flex items-center gap-2 pt-7 text-sm"><input type="hidden" name="insured" value="0"><input type="checkbox" class="checkbox" name="insured" value="1" @checked($v('insured'))> Add insurance cover</label>
                </div>
                <fieldset>
                    <legend class="label">Special handling</legend>
                    <div class="flex flex-wrap gap-4">
                        @foreach(\App\Models\Shipment::HANDLING as $k => $l)
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" name="special_handling[]" value="{{ $k }}" @checked(in_array($k, (array) $v('special_handling', [])))> {{ $l }}</label>
                        @endforeach
                    </div>
                </fieldset>

                {{-- Customs: only for cross-border shipments --}}
                <div x-show="pc && dc && pc !== dc" x-cloak x-transition class="space-y-4 rounded-xl border border-accent-300 bg-accent-50 p-4">
                    <p class="flex items-center gap-2 font-semibold text-ink-900"><x-icon name="globe" class="size-5 text-accent-700" />Customs declaration</p>
                    <p class="text-sm text-ink-700">Required for international shipments. Import duties and taxes are usually paid by the recipient unless arranged otherwise.</p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field name="customs_contents_type" label="Type of contents" type="select" :options="\App\Models\Shipment::CUSTOMS_CONTENTS" :value="$v('customs_contents_type')" />
                        <x-field name="customs_hs_code" label="HS tariff code (optional)" :value="$v('customs_hs_code')" placeholder="e.g. 6109.10" />
                    </div>
                    <x-field name="customs_description" label="Detailed description of contents" type="textarea" rows="2" :value="$v('customs_description')" placeholder="e.g. 2 men's cotton T-shirts, 1 leather wallet" />
                </div>
            </div>
        </section>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <p class="text-sm text-ink-600">Nothing is charged at this step.</p>
            <button type="submit" class="btn btn-primary btn-lg btn-shine">See price & review <x-icon name="arrow-right" class="size-4" /></button>
        </div>
    </form>
</div>
@endsection
