@extends('layouts.portal', ['portal' => 'account'])
@section('title', 'Register your business')
@section('content')
<div class="max-w-2xl">
<x-page-header title="Register your business" subtitle="We review new business accounts before activation — usually within one business day." />
<form method="post" action="{{ route('business.register.store') }}" class="card card-body space-y-4">
    @csrf
    <x-field name="name" label="Registered business name" required />
    <x-field name="registration_number" label="Registration number (RC/BN, optional)" />
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field name="email" label="Business email" type="email" required :value="auth()->user()->email" />
        <x-field name="phone" label="Business phone" type="tel" required :value="auth()->user()->phone" />
    </div>
    <x-field name="billing_email" label="Billing email (optional)" type="email" hint="Invoices are sent here." />
    <x-field name="billing_address" label="Billing address" required />
    <p class="text-sm text-ink-600">You will be the account owner and can invite your team once approved.</p>
    <button class="btn btn-primary" type="submit">Submit for review</button>
</form>
</div>
@endsection
