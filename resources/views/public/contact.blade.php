@extends('layouts.public')
@section('title', 'Contact & support')
@section('content')
<div class="grid gap-8 lg:grid-cols-[1fr_360px]">
    <div>
        <x-page-header title="Contact & support" subtitle="Tell us what's going on and we'll get back to you by email." />
        <form method="post" action="{{ route('support.contact.submit') }}" class="card card-body space-y-4">
            @csrf
            <x-spam-guard />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="contact_name" label="Your name" required autocomplete="name" :value="auth()->user()?->name" />
                <x-field name="contact_email" label="Email" type="email" required autocomplete="email" :value="auth()->user()?->email" />
                <x-field name="contact_phone" label="Phone (optional)" type="tel" autocomplete="tel" :value="auth()->user()?->phone" />
                <x-field name="tracking_number" label="Tracking number (if any)" :value="$tracking" />
            </div>
            <x-field name="category" label="What is this about?" type="select" required :options="\App\Models\SupportTicket::CATEGORIES" />
            <x-field name="subject" label="Subject" required maxlength="150" />
            <x-field name="message" label="Message" type="textarea" rows="6" required hint="Please don't include card numbers or passwords." />
            @error('form')<x-alert type="danger">{{ $message }}</x-alert>@enderror
            <button class="btn btn-primary" type="submit">Send message</button>
        </form>
    </div>
    <aside class="space-y-4 lg:pt-20">
        <div class="card card-body space-y-3 text-sm">
            <h2 class="text-base">Other ways to reach us</h2>
            @if($e = \App\Support\Settings::get('support_email'))<p class="flex items-center gap-2"><x-icon name="mail" class="size-4 text-brand-600" /><a href="mailto:{{ $e }}">{{ $e }}</a></p>@endif
            @if($p = \App\Support\Settings::get('support_phone'))<p class="flex items-center gap-2"><x-icon name="phone" class="size-4 text-brand-600" /><a href="tel:{{ $p }}">{{ $p }}</a></p>@endif
            @if($a = \App\Support\Settings::get('office_address'))<p class="flex items-center gap-2"><x-icon name="map-pin" class="size-4 text-brand-600" />{{ $a }}</p>@endif
            <a href="{{ route('branches') }}" class="btn btn-secondary w-full">Find a branch</a>
        </div>
        <div class="card card-body text-sm">
            <h2 class="text-base">Have an account?</h2>
            <p class="mt-1 text-ink-600">Sign in to see replies and history for all your requests in one place.</p>
            @auth<a href="{{ route('account.support.index') }}" class="btn btn-secondary mt-3 w-full">My support requests</a>@else<a href="{{ route('login') }}" class="btn btn-secondary mt-3 w-full">Sign in</a>@endauth
        </div>
    </aside>
</div>
@endsection
