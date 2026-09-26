@extends('layouts.public')
@section('title', 'Business accounts')
@section('hero')
<x-photo-hero image="forklift" eyebrow="For business" icon="building" title="Ship at volume with a business account" subtitle="Create many shipments at once, manage your team, and keep invoices and reports in one place.">
    @auth
        <a href="{{ route('business.register') }}" class="btn btn-accent btn-lg">Register your business</a>
    @else
        <a href="{{ route('register') }}" class="btn btn-accent btn-lg">Create an account to start</a>
        <a href="{{ route('login') }}" class="btn btn-on-dark btn-lg">Sign in</a>
    @endauth
</x-photo-hero>
@endsection
@section('content')
<div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
    @foreach([
        ['upload', 'accent', 'Bulk CSV upload', 'Download our template, upload, fix any flagged rows, and confirm.'],
        ['team', 'sky', 'Team permissions', 'Owners, admins, shippers, finance and read-only viewers.'],
        ['invoice', 'success', 'Invoices', 'Pay per shipment, or consolidated invoices on approved credit terms.'],
        ['chart', 'violet', 'Reports & exports', 'Delivery performance and spend, exportable to CSV.'],
    ] as $i => [$ill, $tone, $t, $d])
        <div class="card hover-lift p-6" data-reveal style="--d: {{ $i * 90 }}ms">
            <x-illus :name="$ill" :tone="$tone" size="size-12" />
            <h2 class="mt-4 text-lg">{{ $t }}</h2>
            <p class="mt-1 text-sm text-ink-600">{{ $d }}</p>
        </div>
    @endforeach
</div>
<div class="mt-12 grid items-center gap-10 lg:grid-cols-2">
    <img src="{{ asset('images/warehouse-aisle.webp') }}" alt="Parcels stacked in a sorting warehouse" loading="lazy" class="h-80 w-full rounded-3xl object-cover shadow-xl" data-reveal="left">
    <div data-reveal="right">
        <h2 class="text-2xl">How it works</h2>
        <ol class="mt-5 space-y-4">
            @foreach(['Create a personal account, then register your business.', 'We review and approve your business account.', 'Invite your team and add your pickup addresses.', 'Ship one at a time or upload a spreadsheet — track everything in one place.'] as $n => $step)
                <li class="flex gap-4"><span class="grid size-8 shrink-0 place-items-center rounded-full bg-brand-600 text-sm font-bold text-white">{{ $n + 1 }}</span><p class="pt-1 text-ink-700">{{ $step }}</p></li>
            @endforeach
        </ol>
        <p class="mt-6 text-sm text-ink-500">Negotiated rates and invoice terms are agreed individually after approval.</p>
    </div>
</div>
@endsection
