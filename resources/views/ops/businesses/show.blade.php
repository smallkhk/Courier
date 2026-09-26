@extends('layouts.portal', ['portal' => 'ops'])
@section('title', $b->name)
@section('content')
<x-page-header :title="$b->name" :subtitle="$b->email.' · '.$b->phone" :back="route('ops.businesses.index')"><x-pill :tone="$b->status === 'approved' ? 'success' : 'warning'" class="self-center">{{ ucfirst($b->status) }}</x-pill></x-page-header>
<div class="grid gap-6 lg:grid-cols-[1fr_360px]">
    <div class="space-y-6">
        <div class="card card-body grid gap-4 text-sm sm:grid-cols-2">
            <div><p class="text-ink-500">Registration no.</p><p>{{ $b->registration_number ?? '—' }}</p></div>
            <div><p class="text-ink-500">Billing</p><p>{{ $b->billing_email ?? $b->email }}<br>{{ $b->billing_address }}</p></div>
            <div><p class="text-ink-500">Approved</p><p>{{ $b->approved_at?->format('j M Y') ?? '—' }}</p></div>
        </div>
        <div class="card"><div class="card-header"><h2 class="text-base">Team</h2></div><ul class="divide-y divide-ink-100 text-sm">@foreach($b->memberships as $m)<li class="flex justify-between px-6 py-3"><span>{{ $m->user->name }} · {{ $m->user->email }}</span><span>{{ $m->role }}</span></li>@endforeach</ul></div>
        <div class="card"><div class="card-header"><h2 class="text-base">Negotiated rates</h2>@if(auth()->user()->isRole('admin'))<a href="{{ route('admin.resource.create', 'pricing-rules') }}" class="text-sm">Add rule</a>@endif</div>
            <ul class="divide-y divide-ink-100 text-sm">@forelse($rules as $r)<li class="px-6 py-3">@if(auth()->user()->isRole('admin'))<a href="{{ route('admin.resource.edit', ['pricing-rules', $r->id]) }}">{{ $r->name }}</a>@else{{ $r->name }}@endif · {{ $r->service->name }} · base <x-money :amount="$r->base_fee" :currency="$r->currency" /> {{ $r->active ? '' : '(inactive)' }}</li>@empty<li class="px-6 py-3 text-ink-500">Public rates apply.</li>@endforelse</ul></div>
        <div class="card"><div class="card-header"><h2 class="text-base">Recent shipments</h2><a href="{{ route('ops.shipments.index', ['business_id' => $b->id]) }}" class="text-sm">All</a></div>@if($shipments->isEmpty())<p class="p-6 text-sm text-ink-500">None.</p>@else @include('shared.shipment-table', ['shipments' => $shipments, 'showRoute' => 'ops.shipments.show']) @endif</div>
    </div>
    <aside class="space-y-4">
        @if(auth()->user()->isRole('admin'))
        <form method="post" action="{{ route('ops.businesses.update', $b) }}" class="card card-body space-y-3">
            @csrf @method('PUT')
            <h2 class="text-base">Account settings</h2>
            <x-field name="status" label="Status" type="select" :options="['pending'=>'Pending review','approved'=>'Approved','suspended'=>'Suspended','rejected'=>'Rejected']" :value="$b->status" :placeholder="false" />
            <x-field name="payment_terms" label="Payment terms" type="select" :options="['prepaid'=>'Prepaid (pay per shipment)','invoice'=>'Invoice (credit terms)']" :value="$b->payment_terms" :placeholder="false" />
            <x-field name="invoice_due_days" label="Invoice due (days)" type="number" min="0" max="90" :value="$b->invoice_due_days" />
            <button class="btn btn-primary w-full" type="submit">Save</button>
        </form>
        <form method="post" action="{{ route('ops.businesses.invoice', $b) }}" class="card card-body space-y-3">
            @csrf
            <h2 class="text-base">Issue invoice</h2>
            <p class="text-sm text-ink-600">{{ $uninvoiced }} uninvoiced shipment(s) on invoice terms.</p>
            <div class="grid grid-cols-2 gap-2"><x-field name="from" label="From" type="date" :value="now()->subMonthNoOverflow()->startOfMonth()->toDateString()" required /><x-field name="to" label="To" type="date" :value="now()->subMonthNoOverflow()->endOfMonth()->toDateString()" required /></div>
            <button class="btn btn-secondary w-full" type="submit" @disabled(!$uninvoiced)>Generate invoice</button>
        </form>
        @endif
        <div class="card card-body text-sm"><h2 class="text-base">Invoices</h2>@forelse($b->invoices as $i)<p class="mt-2">{{ $i->number }} · <x-money :amount="$i->total" :currency="$i->currency" /> · {{ $i->status }}</p>@empty<p class="mt-2 text-ink-500">None</p>@endforelse</div>
    </aside>
</div>
@endsection
