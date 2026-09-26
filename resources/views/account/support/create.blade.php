@extends('layouts.portal', ['portal' => 'account'])
@section('title', 'New support request')
@section('content')
<div class="max-w-2xl">
<x-page-header title="New support request" :back="route('account.support.index')" />
<form method="post" action="{{ route('account.support.store') }}" class="card card-body space-y-4">
    @csrf
    <x-field name="tracking_number" label="Shipment (optional)" type="select" :options="$shipments->mapWithKeys(fn($t) => [$t => $t])" :value="$tracking" placeholder="Not about a specific shipment" />
    <x-field name="category" label="Category" type="select" required :options="\App\Models\SupportTicket::CATEGORIES" />
    <x-field name="subject" label="Subject" required maxlength="150" />
    <x-field name="message" label="Describe the issue" type="textarea" rows="6" required hint="Don't include card numbers or passwords." />
    <button class="btn btn-primary" type="submit">Submit request</button>
</form>
</div>
@endsection
