@extends('layouts.public')
@section('title', 'Track a parcel')
@section('hero')
<x-photo-hero :tall="true" image="truck-night" eyebrow="Tracking" icon="search" title="Where's my parcel?" subtitle="Enter the tracking number from your confirmation email, SMS or receipt." />
@endsection
@section('content')
<div class="mx-auto max-w-xl">
    <form method="post" action="{{ route('track.lookup') }}" class="card card-body relative z-10 -mt-24 space-y-4 shadow-xl sm:-mt-28" data-reveal>
        @csrf
        <x-field name="tracking_number" label="Tracking number" required maxlength="40" autocomplete="off" spellcheck="false" class="[&_input]:font-mono [&_input]:text-lg [&_input]:uppercase" :value="$notFound ?? ''" />
        <button class="btn btn-primary btn-lg w-full" type="submit">Track</button>
    </form>
    @isset($notFound)
        <x-alert type="warning" class="mt-6" title="We couldn't find that tracking number">
            Check for typos — tracking numbers look like <span class="font-mono">CX7K2M9QH4TRP8</span>. New bookings appear immediately after they're created. If it still doesn't work, <a href="{{ route('support.contact') }}">contact support</a>.
        </x-alert>
    @endisset
</div>
@endsection
