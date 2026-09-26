@extends('layouts.portal', ['portal' => 'ops'])
@section('title', $s->tracking_number)
@section('content')
@php $tz = \App\Support\Settings::get('timezone'); $active = $s->assignments->first(fn($a) => $a->isOpen()); @endphp
<x-page-header :title="$s->tracking_number" :subtitle="$s->service->name.' · created '.$s->created_at->timezone($tz)->format('j M Y H:i')" :back="route('ops.shipments.index')">
    <x-status-badge :status="$s->status" class="self-center text-sm" />
    <a href="{{ route('track.show', $s->tracking_number) }}" class="btn btn-secondary btn-sm" target="_blank">Public view</a>
</x-page-header>
<div class="grid gap-6 xl:grid-cols-[1fr_380px]">
    <div class="space-y-6">
        <div class="card card-body grid gap-6 text-sm break-words md:grid-cols-3 [&>div]:min-w-0 [overflow-wrap:anywhere]">
            <div><h2 class="text-xs font-semibold tracking-wide text-ink-500 uppercase">Sender / pickup</h2>
                <p class="mt-1 font-medium">{{ $s->sender_name }}</p><p>{{ $s->pickup_address }}, {{ $s->pickup_city }}, {{ $s->pickup_state }}</p>
                <p><a href="tel:{{ $s->sender_phone }}">{{ $s->sender_phone }}</a> · {{ $s->sender_email }}</p>
                <p class="text-ink-500">Zone: {{ $s->originZone->name }}</p>
                @if($s->pickup_requested)<p class="mt-1">Pickup {{ $s->pickup_date?->format('D j M') }} {{ \App\Models\Shipment::PICKUP_WINDOWS[$s->pickup_window] ?? '' }}</p>@else<p class="mt-1">Branch drop-off</p>@endif
                @if($s->pickup_instructions)<p class="mt-1 rounded bg-ink-50 p-2">{{ $s->pickup_instructions }}</p>@endif
            </div>
            <div><h2 class="text-xs font-semibold tracking-wide text-ink-500 uppercase">Recipient / delivery</h2>
                <p class="mt-1 font-medium">{{ $s->recipient_name }}</p><p>{{ $s->delivery_address }}, {{ $s->delivery_city }}, {{ $s->delivery_state }}</p>
                <p><a href="tel:{{ $s->recipient_phone }}">{{ $s->recipient_phone }}</a> {{ $s->recipient_email ? '· '.$s->recipient_email : '' }}</p>
                <p class="text-ink-500">Zone: {{ $s->destinationZone->name }}</p>
                @if($s->delivery_instructions)<p class="mt-1 rounded bg-ink-50 p-2">{{ $s->delivery_instructions }}</p>@endif
            </div>
            <div><h2 class="text-xs font-semibold tracking-wide text-ink-500 uppercase">Account & package</h2>
                <p class="mt-1">@if($s->business)<a href="{{ route('ops.businesses.show', $s->business) }}">{{ $s->business->name }}</a>@elseif($s->user)<a href="{{ route('ops.customers.show', $s->user) }}">{{ $s->user->name }}</a>@else Guest @endif</p>
                <p>{{ $s->package_description }} ({{ \App\Models\Shipment::CATEGORIES[$s->package_category] ?? $s->package_category }})</p>
                <p>{{ $s->parcel_count }} parcel(s) · {{ rtrim(rtrim($s->chargeable_weight_kg,'0'),'.') }} kg chargeable</p>
                @if($s->special_handling)<p class="mt-1 flex flex-wrap gap-1">@foreach($s->special_handling as $h)<x-pill tone="warning">{{ \App\Models\Shipment::HANDLING[$h] ?? $h }}</x-pill>@endforeach</p>@endif
                @if($s->insured)<p>Insured · declared <x-money :amount="$s->declared_value" :currency="$s->currency" /></p>@endif
            </div>
        </div>

        <section class="card card-body"><h2 class="mb-6 text-lg">Timeline <span class="text-sm font-normal text-ink-500">(internal view)</span></h2><x-timeline :events="$s->events" :internal="true" /></section>

        @if($s->attempts->isNotEmpty())
        <section class="card"><div class="card-header"><h2 class="text-base">Delivery attempts</h2></div>
            <ul class="divide-y divide-ink-100 text-sm">@foreach($s->attempts as $at)<li class="px-6 py-3">
                <p class="font-medium">{{ \App\Models\DeliveryAttempt::REASONS[$at->reason] }} <span class="font-normal text-ink-500">· {{ $at->attempted_at->timezone($tz)->format('j M H:i') }} · {{ $at->rider?->name ?? 'staff' }}</span></p>
                @if($at->note)<p>{{ $at->note }}</p>@endif
                @if($at->evidence_path)<a href="{{ route('files.attempt', $at) }}" target="_blank">View evidence</a>@endif
                <p class="text-xs text-ink-500">{{ $at->resolution ? 'Decision: '.\App\Models\DeliveryAttempt::RESOLUTIONS[$at->resolution] : 'Awaiting decision — see Exceptions' }}</p>
            </li>@endforeach</ul></section>
        @endif

        @if($s->proofs->isNotEmpty())
        <section class="card card-body"><h2 class="text-base">Proof of delivery</h2>
            @foreach($s->proofs as $p)
                <p class="mt-2 text-sm">Received by <strong>{{ $p->recipient_name }}</strong> · {{ $p->delivered_at->timezone($tz)->format('j M Y H:i') }} · submitted by {{ $p->submitter->name }} @if($p->code_verified)<x-pill tone="success">Code verified</x-pill>@endif</p>
                @if($p->note)<p class="text-sm text-ink-600">{{ $p->note }}</p>@endif
                <div class="mt-2 flex gap-3">
                    @if($p->signature_path)<img src="{{ route('files.proof', [$p, 'signature']) }}" alt="Signature" class="h-24 rounded border border-ink-200 bg-white">@endif
                    @if($p->photo_path)<a href="{{ route('files.proof', [$p, 'photo']) }}" target="_blank"><img src="{{ route('files.proof', [$p, 'photo']) }}" alt="Delivery photo" class="h-24 rounded border border-ink-200"></a>@endif
                </div>
            @endforeach
        </section>
        @endif
    </div>

    <aside class="space-y-4">
        <div class="card card-body space-y-3">
            <h2 class="text-base">Rider</h2>
            @if($active)
                <p class="text-sm"><strong>{{ $active->rider->name }}</strong> ({{ $active->leg }}) — {{ $active->status }} · since {{ $active->assigned_at->diffForHumans() }}</p>
            @else<p class="text-sm text-ink-500">No rider assigned.</p>@endif
            @if(! $s->status->isTerminal() && $s->status->value !== 'pending_payment')
            <form method="post" action="{{ route('ops.shipments.assign', $s) }}" class="space-y-3">
                @csrf
                <x-field name="rider_id" :label="$active ? 'Reassign to' : 'Assign to'" type="select" required :options="$suggested->mapWithKeys(fn($p) => [$p->user_id => $p->user->name.' — '.$p->open_jobs.' open'.($p->in_zone ? ', in zone' : '').($p->on_duty ? ', on duty' : ', off duty')])" placeholder="Choose rider…" hint="Sorted by: in zone, on duty, fewest open jobs (a rule, not route optimisation)." />
                <x-field name="leg" label="Leg" type="select" required :options="['pickup' => 'Pickup', 'delivery' => 'Delivery']" :value="in_array($s->status->value, ['booked','pickup_scheduled']) ? 'pickup' : 'delivery'" :placeholder="false" />
                <button class="btn btn-primary w-full" type="submit">{{ $active ? 'Reassign' : 'Assign' }}</button>
            </form>
            @endif
        </div>

        @if($next->isNotEmpty())
        <form method="post" action="{{ route('ops.shipments.status', $s) }}" class="card card-body space-y-3">
            @csrf
            <h2 class="text-base">Change status</h2>
            <x-field name="status" label="New status" type="select" required :options="$next->mapWithKeys(fn($st) => [$st->value => $st->label()])" />
            <x-field name="public_description" label="Customer-facing message (optional)" maxlength="250" hint="Shown on tracking and in notifications." />
            <x-field name="internal_note" label="Internal note (optional)" type="textarea" rows="2" hint="Never shown to customers." />
            <x-field name="location" label="Location (optional)" />
            <button class="btn btn-secondary w-full" type="submit">Update status</button>
        </form>
        @endif

        @if($canDeliver)
        <form method="post" action="{{ route('ops.shipments.proof', $s) }}" class="card card-body space-y-3" x-data="{open:false}">
            @csrf
            <button type="button" class="flex w-full justify-between text-left font-semibold" @click="open=!open" :aria-expanded="open.toString()">Record delivery (staff) <x-icon name="chevron-down" class="size-5" /></button>
            <div x-show="open" x-cloak class="space-y-3">
                <x-field name="recipient_name" label="Received by" required />
                <x-field name="note" label="How was it verified?" required hint="e.g. Collected at Ikeja branch, ID checked." />
                @if($s->payment_method === 'cod')<x-field name="cod_amount_collected" label="Cash collected" type="number" step="0.01" required :value="$s->cod_amount" />@endif
                <button class="btn btn-primary w-full" type="submit">Mark delivered</button>
            </div>
        </form>
        @endif

        <form method="post" action="{{ route('ops.shipments.note', $s) }}" class="card card-body space-y-3">
            @csrf
            <h2 class="text-base">Add note</h2>
            <x-field name="internal_note" label="Internal note" type="textarea" rows="2" required />
            <button class="btn btn-secondary w-full" type="submit">Add note</button>
        </form>

        <div class="card card-body text-sm">
            <h2 class="text-base">Payment</h2>
            <p class="mt-1 uppercase">{{ $s->payment_method }} · <x-money :amount="$s->total" :currency="$s->currency" /></p>
            @foreach($s->payments as $p)<p class="mt-1"><a href="{{ route('ops.payments.show', $p) }}" class="font-mono text-xs">{{ $p->reference }}</a> <x-pill :tone="$p->status === 'successful' ? 'success' : 'neutral'">{{ $p->status }}</x-pill></p>@endforeach
            @if($s->codCollection)<p class="mt-2">COD: {{ $s->codCollection->status }} · due <x-money :amount="$s->codCollection->amount_due" :currency="$s->currency" /></p>@endif
            @if(auth()->user()->isRole('admin') && $s->status->value === 'pending_payment')
                <form method="post" action="{{ route('ops.shipments.confirm-offline', $s) }}" class="mt-3 space-y-2 border-t border-ink-200 pt-3" x-data="{open:false}">
                    @csrf
                    <button type="button" class="text-sm font-medium text-brand-700" @click="open=!open">Confirm offline payment…</button>
                    <div x-show="open" x-cloak class="space-y-2">
                        <x-field name="reference" label="Bank / receipt reference" required />
                        <x-field name="note" label="Note" required />
                        <button class="btn btn-secondary w-full" type="submit">Confirm booking</button>
                    </div>
                </form>
            @endif
            <div class="mt-3 border-t border-ink-200 pt-3"><x-price-breakdown :breakdown="$s->price_breakdown" :currency="$s->currency" /></div>
        </div>

        <div class="card card-body text-sm"><h2 class="text-base">Assignment history</h2>
            <ul class="mt-2 space-y-1">@forelse($s->assignments as $a)<li>{{ $a->rider->name }} · {{ $a->leg }} · {{ $a->status }} <span class="text-ink-500">({{ $a->assigned_at->timezone($tz)->format('j M H:i') }} by {{ $a->assigner?->name ?? 'system' }})</span></li>@empty<li class="text-ink-500">None</li>@endforelse</ul>
        </div>
    </aside>
</div>
@endsection
