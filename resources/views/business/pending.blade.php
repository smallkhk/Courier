@extends('layouts.portal', ['portal' => 'business'])
@section('title', 'Business account')
@section('content')
<div class="card card-body mx-auto max-w-xl text-center">
    <span class="mx-auto grid size-14 place-items-center rounded-full bg-warning-50 text-warning-800"><x-icon name="clock" class="size-7" /></span>
    <h1 class="mt-4 text-2xl">{{ $business->name }}</h1>
    <p class="mt-2"><x-pill :tone="$business->status === 'pending' ? 'warning' : 'danger'">{{ ucfirst($business->status) }}</x-pill></p>
    <p class="mt-4 text-ink-600">
        @if($business->status === 'pending')Your business account is being reviewed. You'll get an email when it's approved. In the meantime you can still send parcels from your personal account.
        @else This business account is {{ $business->status }}. Please contact support for help.@endif
    </p>
    <div class="mt-6 flex justify-center gap-2"><a href="{{ route('account.dashboard') }}" class="btn btn-secondary">Personal account</a><a href="{{ route('support.contact') }}" class="btn btn-ghost">Contact support</a></div>
</div>
@endsection
