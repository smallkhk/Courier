@extends('layouts.public')
@section('title', 'Business accounts')
@section('content')
<div class="grid items-center gap-10 lg:grid-cols-2">
    <div>
        <x-pill tone="accent" icon="building">For business</x-pill>
        <h1 class="mt-4 text-[length:var(--text-fluid-h1)] leading-tight">Ship at volume with a business account</h1>
        <p class="mt-4 text-lg text-ink-600">Create many shipments at once, manage your team, and keep invoices and reports in one place.</p>
        <div class="mt-8 flex flex-wrap gap-3">
            @auth
                <a href="{{ route('business.register') }}" class="btn btn-primary btn-lg">Register your business</a>
            @else
                <a href="{{ route('register') }}" class="btn btn-primary btn-lg">Create an account to start</a>
                <a href="{{ route('login') }}" class="btn btn-secondary btn-lg">Sign in</a>
            @endauth
        </div>
        <p class="mt-3 text-sm text-ink-500">Business accounts are reviewed before activation. Negotiated rates and invoice terms are agreed individually.</p>
    </div>
    <ul class="grid gap-4 sm:grid-cols-2">
        @foreach([
            ['upload', 'Bulk CSV upload', 'Download our template, upload, fix any flagged rows, and confirm.'],
            ['users', 'Team permissions', 'Owners, admins, shippers, finance and read-only viewers.'],
            ['receipt', 'Invoices', 'Pay per shipment, or consolidated invoices on approved credit terms.'],
            ['chart', 'Reports & exports', 'Delivery performance and spend, exportable to CSV.'],
        ] as [$icon, $t, $d])
            <li class="card card-body"><x-icon :name="$icon" class="size-6 text-brand-600" /><h2 class="mt-3 text-base">{{ $t }}</h2><p class="mt-1 text-sm text-ink-600">{{ $d }}</p></li>
        @endforeach
    </ul>
</div>
@endsection
