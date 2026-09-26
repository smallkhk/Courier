@extends('layouts.public')
@section('title', 'Track a parcel')
@section('content')
<div class="mx-auto max-w-xl">
    <div class="text-center">
        <span class="mx-auto grid size-14 place-items-center rounded-full bg-brand-50 text-brand-600"><x-icon name="search" class="size-7" /></span>
        <h1 class="mt-4 text-3xl">Track a parcel</h1>
        <p class="mt-2 text-ink-600">Enter the tracking number from your confirmation email, SMS or receipt.</p>
    </div>
    @isset($notFound)
        <x-alert type="warning" class="mt-6" title="We couldn't find that tracking number">
            Check for typos — tracking numbers look like <span class="font-mono">CX7K2M9QH4TRP8</span>. New bookings appear immediately after they're created. If it still doesn't work, <a href="{{ route('support.contact') }}">contact support</a>.
        </x-alert>
    @endisset
    <form method="post" action="{{ route('track.lookup') }}" class="card card-body mt-6 space-y-4">
        @csrf
        <x-field name="tracking_number" label="Tracking number" required maxlength="40" autocomplete="off" spellcheck="false" class="[&_input]:font-mono [&_input]:text-lg [&_input]:uppercase" :value="$notFound ?? ''" />
        <button class="btn btn-primary btn-lg w-full" type="submit">Track</button>
    </form>
</div>
@endsection
