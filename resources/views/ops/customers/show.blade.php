@extends('layouts.portal', ['portal' => 'ops'])
@section('title', $u->name)
@section('content')
<x-page-header :title="$u->name" :subtitle="$u->email.' · '.$u->phone" :back="route('ops.customers.index')">
    @if(auth()->user()->isRole('admin'))<a href="{{ route('admin.users.edit', $u) }}" class="btn btn-secondary">Manage account</a>@endif
</x-page-header>
<div class="grid gap-6 lg:grid-cols-[1fr_320px]">
    <div class="card">@if($shipments->isEmpty())<x-empty icon="package" title="No personal shipments" />@else @include('shared.shipment-table', ['shipments' => $shipments, 'showRoute' => 'ops.shipments.show']) @endif</div>
    <aside class="space-y-4 text-sm">
        <div class="card card-body"><h2 class="text-base">Account</h2>
            <p class="mt-2">Status: {{ $u->status }}</p><p>Email verified: {{ $u->email_verified_at ? 'yes' : 'no' }}</p><p>SMS consent: {{ $u->sms_consent_at ? 'yes ('.$u->sms_consent_at->format('j M Y').')' : 'no' }}</p>
            <p>Last login: {{ $u->last_login_at?->diffForHumans() ?? 'never' }}</p>
            @foreach($u->businesses as $b)<p class="mt-1">Business: <a href="{{ route('ops.businesses.show', $b) }}">{{ $b->name }}</a> ({{ $b->pivot->role }})</p>@endforeach</div>
        <div class="card card-body"><h2 class="text-base">Saved addresses</h2>@forelse($u->addresses as $a)<p class="mt-2">{{ $a->label }}: {{ $a->oneLine() }}</p>@empty<p class="mt-2 text-ink-500">None</p>@endforelse</div>
    </aside>
</div>
@endsection
