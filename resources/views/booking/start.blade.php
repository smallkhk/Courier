@extends('layouts.public')
@section('title', 'Send a parcel')
@section('content')
@php
    $v = fn($k, $d = null) => old($k, $details[$k] ?? $d);
    $zoneOptions = $zones->mapWithKeys(fn($z) => [$z->id => $z->name.' — '.$z->state]);
    $parcelRows = old('parcels', $details['parcels'] ?? []);
@endphp
<div class="mx-auto max-w-4xl">
    @include('booking._steps', ['current' => 1])
    <x-page-header title="Send a parcel" subtitle="We'll check the route and show you the full price before you pay." />
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

    <form method="post" action="{{ route('book.quote') }}" class="space-y-6" novalidate>
        @csrf
        <section class="card" aria-labelledby="pickup-h">
            <div class="card-header"><h2 id="pickup-h" class="flex items-center gap-2 text-lg"><span class="grid size-7 place-items-center rounded-full bg-brand-600 text-sm text-white">1</span>Pickup — sender</h2>
                @if($addresses->isNotEmpty())
                    <div x-data="addressPicker(@js($addresses), 'pickup')">
                        <label for="pick-from" class="sr-only">Use a saved address</label>
                        <select id="pick-from" class="input min-h-9 py-1 text-sm" @change="pick($event.target.value)"><option value="">Use a saved address…</option>@foreach($addresses as $a)<option value="{{ $a->id }}">{{ $a->label }} — {{ $a->city }}</option>@endforeach</select>
                    </div>
                @endif
            </div>
            <div class="card-body grid gap-4 sm:grid-cols-2">
                <x-field name="sender_name" label="Sender name" required autocomplete="name" :value="$v('sender_name')" />
                <x-field name="sender_phone" label="Sender phone" type="tel" required autocomplete="tel" :value="$v('sender_phone')" />
                <x-field name="sender_email" label="Sender email" type="email" required autocomplete="email" :value="$v('sender_email')" hint="Booking confirmation and receipts go here." class="sm:col-span-2" />
                <x-field name="pickup_address" label="Street address" required autocomplete="street-address" :value="$v('pickup_address')" class="sm:col-span-2" />
                <x-field name="pickup_city" label="City / area" required :value="$v('pickup_city')" />
                <x-field name="pickup_state" label="State" required :value="$v('pickup_state')" />
                <x-field name="origin_zone_id" label="Coverage area" type="select" required :options="$zoneOptions" :value="$v('origin_zone_id')" class="sm:col-span-2" hint="Not sure? See our coverage page." />
                <x-field name="pickup_instructions" label="Pickup instructions (optional)" type="textarea" rows="2" :value="$v('pickup_instructions')" class="sm:col-span-2" />
                <div class="sm:col-span-2 rounded-lg bg-ink-50 p-4" x-data="{ pickup: {{ $v('pickup_requested', '1') ? 'true' : 'false' }} }">
                    <label class="flex items-center gap-2 font-medium"><input type="hidden" name="pickup_requested" value="0"><input type="checkbox" class="checkbox" name="pickup_requested" value="1" x-model="pickup"> Collect from this address</label>
                    <p class="hint">Untick to drop the parcel off at a branch instead.</p>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2" x-show="pickup" x-cloak>
                        <x-field name="pickup_date" label="Preferred pickup date" type="date" :value="$v('pickup_date', now()->addDay()->toDateString())" min="{{ now()->toDateString() }}" max="{{ now()->addDays(29)->toDateString() }}" />
                        <x-field name="pickup_window" label="Time window" type="select" :options="\App\Models\Shipment::PICKUP_WINDOWS" :value="$v('pickup_window')" placeholder="Any time" />
                    </div>
                </div>
            </div>
        </section>

        <section class="card" aria-labelledby="deliv-h">
            <div class="card-header"><h2 id="deliv-h" class="flex items-center gap-2 text-lg"><span class="grid size-7 place-items-center rounded-full bg-brand-600 text-sm text-white">2</span>Delivery — recipient</h2>
                @if($addresses->isNotEmpty())
                    <div x-data="addressPicker(@js($addresses), 'delivery')">
                        <label for="pick-to" class="sr-only">Use a saved address</label>
                        <select id="pick-to" class="input min-h-9 py-1 text-sm" @change="pick($event.target.value)"><option value="">Use a saved address…</option>@foreach($addresses as $a)<option value="{{ $a->id }}">{{ $a->label }} — {{ $a->city }}</option>@endforeach</select>
                    </div>
                @endif
            </div>
            <div class="card-body grid gap-4 sm:grid-cols-2">
                <x-field name="recipient_name" label="Recipient name" required :value="$v('recipient_name')" />
                <x-field name="recipient_phone" label="Recipient phone" type="tel" required :value="$v('recipient_phone')" hint="Used by the rider and for delivery updates." />
                <x-field name="recipient_email" label="Recipient email (optional)" type="email" :value="$v('recipient_email')" class="sm:col-span-2" />
                <x-field name="delivery_address" label="Street address" required :value="$v('delivery_address')" class="sm:col-span-2" />
                <x-field name="delivery_city" label="City / area" required :value="$v('delivery_city')" />
                <x-field name="delivery_state" label="State" required :value="$v('delivery_state')" />
                <x-field name="destination_zone_id" label="Coverage area" type="select" required :options="$zoneOptions" :value="$v('destination_zone_id')" class="sm:col-span-2" />
                <x-field name="delivery_instructions" label="Delivery instructions (optional)" type="textarea" rows="2" :value="$v('delivery_instructions')" class="sm:col-span-2" placeholder="Gate code, landmark, best time…" />
            </div>
        </section>

        <section class="card" aria-labelledby="pkg-h">
            <div class="card-header"><h2 id="pkg-h" class="flex items-center gap-2 text-lg"><span class="grid size-7 place-items-center rounded-full bg-brand-600 text-sm text-white">3</span>Package & service</h2></div>
            <div class="card-body space-y-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="package_description" label="What are you sending?" required :value="$v('package_description')" placeholder="e.g. 2 pairs of shoes" />
                    <x-field name="package_category" label="Category" type="select" required :options="\App\Models\Shipment::CATEGORIES" :value="$v('package_category')" />
                </div>
                <fieldset x-data="parcels(@js(array_values($parcelRows)))">
                    <legend class="label">Parcels</legend>
                    <div class="space-y-3">
                        <template x-for="(row, i) in rows" :key="i">
                            <div class="grid grid-cols-2 items-end gap-3 rounded-lg border border-ink-200 p-3 sm:grid-cols-[repeat(4,1fr)_auto]">
                                <div><label class="label" :for="'w'+i">Weight (kg) <span class="text-danger-700" aria-hidden="true">*</span></label><input class="input" type="number" step="0.01" min="0.01" required :id="'w'+i" :name="'parcels['+i+'][weight_kg]'" x-model="row.weight_kg"></div>
                                <div><label class="label" :for="'l'+i">Length (cm)</label><input class="input" type="number" step="0.1" min="1" :id="'l'+i" :name="'parcels['+i+'][length_cm]'" x-model="row.length_cm"></div>
                                <div><label class="label" :for="'wd'+i">Width (cm)</label><input class="input" type="number" step="0.1" min="1" :id="'wd'+i" :name="'parcels['+i+'][width_cm]'" x-model="row.width_cm"></div>
                                <div><label class="label" :for="'h'+i">Height (cm)</label><input class="input" type="number" step="0.1" min="1" :id="'h'+i" :name="'parcels['+i+'][height_cm]'" x-model="row.height_cm"></div>
                                <button type="button" class="btn btn-ghost col-span-2 sm:col-span-1" @click="remove(i)" x-show="rows.length > 1" :aria-label="'Remove parcel '+(i+1)"><x-icon name="x" class="size-4" /><span class="sm:sr-only">Remove</span></button>
                            </div>
                        </template>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm mt-3" @click="add()"><x-icon name="plus" class="size-4" />Add another parcel</button>
                    @if($errors->has('parcels*'))<p class="error-text"><x-icon name="alert" class="size-4" />Check the weight and size of each parcel.</p>@endif
                </fieldset>
                <x-field name="service_id" label="Delivery service" type="select" required :options="$services->mapWithKeys(fn($s) => [$s->id => $s->name.($s->transitLabel() ? ' — '.$s->transitLabel() : '')])" :value="$v('service_id')" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="declared_value" label="Declared value (optional)" type="number" step="0.01" min="0" :value="$v('declared_value')" hint="Used for insurance and claims." />
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
            </div>
        </section>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <p class="text-sm text-ink-600">Nothing is charged at this step.</p>
            <button type="submit" class="btn btn-primary btn-lg">See price & review <x-icon name="arrow-right" class="size-4" /></button>
        </div>
    </form>
</div>
@endsection
