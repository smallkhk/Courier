@extends('layouts.portal', ['portal' => 'business'])
@section('title', 'Company profile')
@section('content')
<div class="max-w-2xl">
<x-page-header title="Company profile" />
<form method="post" action="{{ route('business.profile.update') }}" class="card card-body space-y-4">
    @csrf @method('PUT')
    <x-field name="name" label="Business name" required :value="$business->name" />
    <x-field name="registration_number" label="Registration number" :value="$business->registration_number" />
    <div class="grid gap-4 sm:grid-cols-2"><x-field name="email" label="Email" type="email" required :value="$business->email" /><x-field name="phone" label="Phone" required :value="$business->phone" /></div>
    <x-field name="billing_email" label="Billing email" type="email" :value="$business->billing_email" />
    <x-field name="billing_address" label="Billing address" required :value="$business->billing_address" />
    <p class="text-sm text-ink-600">Payment terms: <strong>{{ $business->billsByInvoice() ? 'Invoice' : 'Prepaid' }}</strong>. Contact us to discuss negotiated rates or invoice terms.</p>
    <button class="btn btn-primary" type="submit">Save</button>
</form>
</div>
@endsection
