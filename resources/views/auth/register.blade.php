@extends('layouts.public')
@section('title', 'Create account')
@section('content')
<div class="mx-auto max-w-md">
    <div class="mb-6 text-center">
        <span class="mx-auto grid size-12 place-items-center rounded-xl bg-brand-600 text-white"><x-icon name="user" /></span>
        <h1 class="mt-4 text-2xl">Create your account</h1>
        <p class="mt-1 text-ink-600">Book faster, save addresses and see all your shipments.</p>
    </div>
    <form method="post" action="{{ route('register') }}" class="card card-body space-y-4">
        @csrf
        <x-spam-guard />
        <x-field name="name" label="Full name" required autocomplete="name" />
        <x-field name="email" label="Email" type="email" required autocomplete="email" />
        <x-field name="phone" label="Phone number" type="tel" required autocomplete="tel" placeholder="0803 000 0000" />
        <x-field name="password" label="Password" type="password" required autocomplete="new-password" hint="At least 10 characters, with letters and numbers." />
        <x-field name="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password" />
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="sms_consent" value="1" class="checkbox mt-0.5" @checked(old('sms_consent'))> Send me shipment updates by SMS</label>
        <label class="flex items-start gap-2 text-sm">
            <input type="checkbox" name="accept_terms" value="1" class="checkbox mt-0.5" required @checked(old('accept_terms'))>
            <span>I agree to the <a href="{{ route('legal', 'terms') }}" target="_blank">terms of service</a> and <a href="{{ route('legal', 'privacy') }}" target="_blank">privacy policy</a>.</span>
        </label>
        @error('accept_terms')<p class="error-text">{{ $message }}</p>@enderror
        @error('form')<x-alert type="danger">{{ $message }}</x-alert>@enderror
        <button class="btn btn-primary btn-lg w-full" type="submit">Create account</button>
    </form>
    <p class="mt-6 text-center text-sm text-ink-600">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
</div>
@endsection
