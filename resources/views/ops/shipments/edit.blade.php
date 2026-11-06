@extends('layouts.portal', ['portal' => 'ops'])
@section('title', 'Edit '.$s->tracking_number)
@section('content')
@php $countries = \App\Support\Countries::options(); @endphp
<x-page-header :title="'Edit '.$s->tracking_number" subtitle="Correct the customer's contact and address details." :back="route('ops.shipments.show', $s)" icon="file" />

<x-alert type="info" class="mb-6">The price already quoted or paid stays the same. Every change is recorded on the shipment timeline (staff only) and in the audit log with your reason.</x-alert>

<div class="mb-6 rounded-xl border border-warning-200 bg-warning-50 p-4" x-data="{ open: false }">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="text-sm text-warning-800"><p class="font-semibold">Sender and recipient the wrong way round?</p><p>Swap names, phones, emails and addresses in one step.</p></div>
        <button type="button" class="btn btn-secondary" @click="open = !open"><x-icon name="undo" class="size-4" />Swap sender &amp; recipient</button>
    </div>
    <form x-show="open" x-cloak method="post" action="{{ route('ops.shipments.swap', $s) }}" class="mt-4 grid gap-3 sm:flex sm:items-end">
        @csrf
        <x-field name="reason" label="Reason" required placeholder="Customer entered sender and recipient the wrong way round" class="sm:flex-1" />
        <button class="btn btn-primary" type="submit">Swap now</button>
    </form>
</div>

<form method="post" action="{{ route('ops.shipments.update', $s) }}" class="space-y-6">
    @csrf @method('PUT')
    <div class="grid gap-6 lg:grid-cols-2">
        @foreach(['pickup' => ['sender', 'Sender / pickup'], 'delivery' => ['recipient', 'Recipient / delivery']] as $side => [$who, $title])
            <section class="card">
                <div class="card-header"><h2 class="text-base">{{ $title }}</h2></div>
                <div class="card-body grid gap-4 sm:grid-cols-2">
                    <x-field :name="$who.'_name'" label="Full name" :value="$s->{$who.'_name'}" required class="sm:col-span-2" />
                    <x-field :name="$who.'_phone'" label="Phone" type="tel" :value="\App\Support\Phone::display($s->{$who.'_phone'})" required />
                    <x-field :name="$who.'_email'" label="Email" type="email" :value="$s->{$who.'_email'}" :required="$who === 'sender'" />
                    <x-field :name="$side.'_country'" label="Country" type="select" :options="$countries" :value="$s->{$side.'_country'}" required :placeholder="false" class="sm:col-span-2" />
                    <x-field :name="$side.'_address'" label="Street address" :value="$s->{$side.'_address'}" required class="sm:col-span-2" />
                    <x-field :name="$side.'_address2'" label="Apt, suite, unit" :value="$s->{$side.'_address2'}" class="sm:col-span-2" />
                    <x-field :name="$side.'_city'" label="City / town" :value="$s->{$side.'_city'}" required />
                    <x-field :name="$side.'_region'" label="State / region" :value="$s->{$side.'_region'}" hint="US/Canada: name or 2-letter code" />
                    <x-field :name="$side.'_postal_code'" label="ZIP / postal code" :value="$s->{$side.'_postal_code'}" />
                    <x-field :name="$side.'_instructions'" :label="$side === 'pickup' ? 'Pickup instructions' : 'Delivery instructions'" type="textarea" rows="2" :value="$s->{$side.'_instructions'}" class="sm:col-span-2" />
                </div>
            </section>
        @endforeach
    </div>
    <section class="card card-body grid gap-4 sm:grid-cols-2">
        <x-field name="package_description" label="What's being sent" :value="$s->package_description" required />
        <x-field name="reason" label="Reason for the change" required placeholder="e.g. Customer called: recipient phone was wrong" />
    </section>
    <div class="flex gap-3">
        <button class="btn btn-primary btn-lg" type="submit">Save changes</button>
        <a href="{{ route('ops.shipments.show', $s) }}" class="btn btn-ghost btn-lg">Cancel</a>
    </div>
</form>
@endsection
