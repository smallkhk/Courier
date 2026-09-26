@extends('layouts.portal', ['portal' => 'rider'])
@section('title', $s->tracking_number)
@section('content')
@php
    $isPickup = $a->leg === 'pickup';
    $addr = $isPickup ? "{$s->pickup_address}, {$s->pickup_city}, {$s->pickup_state}" : "{$s->delivery_address}, {$s->delivery_city}, {$s->delivery_state}";
    $contactName = $isPickup ? $s->sender_name : $s->recipient_name;
    $contactPhone = $isPickup ? $s->sender_phone : $s->recipient_phone;
    $instructions = $isPickup ? $s->pickup_instructions : $s->delivery_instructions;
@endphp
<div class="mx-auto max-w-2xl space-y-5">
    <x-page-header :title="$s->tracking_number" :subtitle="ucfirst($a->leg).' · '.$s->service->name" :back="route('rider.dashboard')">
        <x-status-badge :status="$s->status" class="self-center" />
    </x-page-header>

    @if($a->status === 'assigned')
        <div class="card card-body border-accent-300 bg-accent-50">
            <p class="font-semibold">New assignment</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <form method="post" action="{{ route('rider.jobs.respond', $a) }}">@csrf<input type="hidden" name="accept" value="1"><button class="btn btn-primary" type="submit">Accept job</button></form>
                <form method="post" action="{{ route('rider.jobs.respond', $a) }}" x-data="{ open: false }" class="flex flex-wrap gap-2">@csrf<input type="hidden" name="accept" value="0">
                    <button type="button" class="btn btn-secondary" @click="open = true" x-show="!open">Decline</button>
                    <template x-if="open"><div class="flex gap-2"><label class="sr-only" for="dr">Reason</label><input id="dr" name="reason" class="input" placeholder="Reason" required><button class="btn btn-danger" type="submit">Confirm decline</button></div></template>
                </form>
            </div>
        </div>
    @endif

    <section class="card card-body">
        <h2 class="text-xs font-semibold tracking-wide text-ink-500 uppercase">{{ $isPickup ? 'Collect from' : 'Deliver to' }}</h2>
        <p class="mt-2 text-lg font-semibold">{{ $contactName }}</p>
        <p class="text-ink-700">{{ $addr }}</p>
        @if($instructions)<p class="mt-2 rounded bg-ink-50 p-2 text-sm"><strong>Instructions:</strong> {{ $instructions }}</p>@endif
        @if($s->special_handling)<p class="mt-2 flex flex-wrap gap-1">@foreach($s->special_handling as $h)<x-pill tone="warning" icon="alert">{{ \App\Models\Shipment::HANDLING[$h] ?? $h }}</x-pill>@endforeach</p>@endif
        <div class="mt-4 grid grid-cols-2 gap-2">
            <a href="tel:{{ $contactPhone }}" class="btn btn-secondary"><x-icon name="phone" class="size-4" />Call</a>
            <a href="https://www.google.com/maps/dir/?api=1&destination={{ urlencode($addr) }}" target="_blank" rel="noopener" class="btn btn-secondary"><x-icon name="navigation" class="size-4" />Navigate</a>
        </div>
        <p class="mt-3 text-sm text-ink-600">{{ $s->parcel_count }} parcel(s), {{ rtrim(rtrim($s->chargeable_weight_kg, '0'), '.') }} kg · {{ $s->package_description }}</p>
        @if($s->payment_method === 'cod')<p class="mt-2 rounded border border-accent-300 bg-accent-50 p-2 text-sm font-semibold text-accent-800">Collect cash: <x-money :amount="$s->cod_amount" :currency="$s->currency" /></p>@endif
    </section>

    @if($a->isOpen() && $next->isNotEmpty())
    <form method="post" action="{{ route('rider.jobs.status', $a) }}" class="card card-body space-y-3">
        @csrf
        <h2 class="text-base">Update status</h2>
        <div class="grid gap-2 sm:grid-cols-2">
            @foreach($next as $st)
                <label class="flex items-center gap-2 rounded-lg border border-ink-200 p-3 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                    <input type="radio" name="status" value="{{ $st->value }}" required class="accent-brand-600"> <x-icon :name="$st->icon()" class="size-4" />{{ $st->label() }}
                </label>
            @endforeach
        </div>
        <x-field name="note" label="Note for operations (optional)" />
        <button class="btn btn-primary w-full" type="submit">Update</button>
    </form>
    @endif

    @if($canDeliver)
    <form method="post" action="{{ route('rider.jobs.proof', $a) }}" enctype="multipart/form-data" class="card card-body space-y-4" x-data="{ photo: false }">
        @csrf
        <h2 class="flex items-center gap-2 text-base"><x-icon name="check-circle" class="size-5 text-success-600" />Proof of delivery</h2>
        <x-field name="recipient_name" label="Received by (full name)" required />
        @if($requireCode)<x-field name="delivery_code" label="Recipient's 6-digit delivery code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" required />@endif
        <div x-data="signaturePad()">
            <p class="label">Recipient signature @if($requireSignature)<span class="text-danger-700" aria-hidden="true">*</span>@else<span class="font-normal text-ink-500">(optional)</span>@endif</p>
            <canvas x-ref="canvas" class="h-40 w-full touch-none rounded-lg border-2 border-dashed border-ink-300 bg-white" aria-label="Signature area — sign with your finger"></canvas>
            <input type="hidden" name="signature" x-ref="input">
            <button type="button" class="btn btn-ghost btn-sm mt-1" @click="clear()">Clear signature</button>
            @error('signature')<p class="error-text">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="photo" class="label">Delivery photo @if($requirePhoto)<span class="text-danger-700" aria-hidden="true">*</span>@else<span class="font-normal text-ink-500">(optional)</span>@endif</label>
            <input id="photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" capture="environment" class="input py-2" @change="photo = $event.target.files.length > 0">
            @error('photo')<p class="error-text">{{ $message }}</p>@enderror
            <label class="mt-2 flex items-start gap-2 text-sm" x-show="photo" x-cloak><input type="checkbox" name="photo_consent" value="1" class="checkbox mt-0.5"> The recipient agreed to the photo being taken.</label>
            @error('photo_consent')<p class="error-text">{{ $message }}</p>@enderror
        </div>
        @if($s->payment_method === 'cod')<x-field name="cod_amount_collected" label="Cash collected" type="number" step="0.01" min="0" required :value="$s->cod_amount" />@endif
        <x-field name="note" label="Delivery note (optional)" />
        <button class="btn btn-primary btn-lg w-full" type="submit">Mark delivered</button>
    </form>

    <form method="post" action="{{ route('rider.jobs.failed', $a) }}" enctype="multipart/form-data" class="card card-body space-y-4" x-data="{ open: false }">
        @csrf
        <button type="button" class="flex w-full items-center justify-between text-left" @click="open = !open" :aria-expanded="open.toString()">
            <span class="flex items-center gap-2 text-base font-semibold"><x-icon name="alert" class="size-5 text-warning-600" />Couldn't deliver?</span><x-icon name="chevron-down" class="size-5" />
        </button>
        <div x-show="open" x-cloak class="space-y-4">
            <x-field name="reason" label="Reason" type="select" required :options="\App\Models\DeliveryAttempt::REASONS" />
            <x-field name="note" label="Note (may be shared with the customer)" maxlength="250" />
            <div><label for="evidence" class="label">Photo evidence (optional)</label><input id="evidence" type="file" name="evidence" accept="image/*" capture="environment" class="input py-2"></div>
            <button class="btn btn-danger w-full" type="submit">Record failed attempt</button>
        </div>
    </form>
    @endif

    <section class="card card-body"><h2 class="mb-4 text-base">History</h2><x-timeline :events="$s->events" /></section>
</div>
@endsection
