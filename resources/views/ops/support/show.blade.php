@extends('layouts.portal', ['portal' => 'ops'])
@section('title', $t->reference)
@section('content')
<x-page-header :title="$t->subject" :subtitle="$t->reference.' · '.\App\Models\SupportTicket::CATEGORIES[$t->category]" :back="route('ops.support.index')" />
<div class="grid gap-6 lg:grid-cols-[1fr_340px]">
    <div class="space-y-4">
        @foreach($t->messages as $m)
            <div class="card card-body {{ $m->is_internal ? 'border-warning-200 bg-warning-50' : ($m->author?->isStaff() ? 'border-brand-200 bg-brand-50/40' : '') }}">
                <p class="text-xs font-semibold text-ink-500">{{ $m->author?->name ?? $t->contact_name }} @if($m->is_internal)<x-pill tone="warning">Internal note</x-pill>@endif · {{ $m->created_at->diffForHumans() }}</p>
                <p class="mt-2 whitespace-pre-line">{{ $m->body }}</p>
            </div>
        @endforeach
        <form method="post" action="{{ route('ops.support.reply', $t) }}" class="card card-body space-y-3">
            @csrf
            <x-field name="body" label="Message" type="textarea" rows="4" required />
            <div class="flex flex-wrap items-end gap-3">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" name="internal" value="1"> Internal note (not sent to customer)</label>
                <x-field name="status" label="Set status" type="select" :options="\App\Models\SupportTicket::STATUSES" placeholder="Keep current" />
                <button class="btn btn-primary" type="submit">Send</button>
            </div>
        </form>
    </div>
    <aside class="space-y-4">
        <div class="card card-body text-sm">
            <h2 class="text-base">Customer</h2>
            <p class="mt-2">{{ $t->contact_name }}</p><p><a href="mailto:{{ $t->contact_email }}">{{ $t->contact_email }}</a></p>
            @if($t->contact_phone)<p><a href="tel:{{ $t->contact_phone }}">{{ $t->contact_phone }}</a></p>@endif
            @if($t->user)<p class="mt-1"><a href="{{ route('ops.customers.show', $t->user) }}">View account</a></p>@else<p class="text-ink-500">Guest</p>@endif
            @if($t->shipment)<p class="mt-2">Shipment: <a href="{{ route('ops.shipments.show', $t->shipment) }}" class="font-mono">{{ $t->shipment->tracking_number }}</a></p>@endif
        </div>
        <form method="post" action="{{ route('ops.support.update', $t) }}" class="card card-body space-y-3">
            @csrf @method('PUT')
            <x-field name="status" label="Status" type="select" :options="\App\Models\SupportTicket::STATUSES" :value="$t->status" :placeholder="false" />
            <x-field name="priority" label="Priority" type="select" :options="\App\Models\SupportTicket::PRIORITIES" :value="$t->priority" :placeholder="false" />
            <x-field name="assigned_to" label="Assignee" type="select" :options="$staff" :value="$t->assigned_to" placeholder="Unassigned" />
            <button class="btn btn-secondary w-full" type="submit">Update ticket</button>
        </form>
    </aside>
</div>
@endsection
