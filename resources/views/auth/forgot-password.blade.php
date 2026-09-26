@extends('layouts.public')
@section('title', 'Reset password')
@section('content')
<div class="mx-auto max-w-md">
    <h1 class="text-center text-2xl">Reset your password</h1>
    <p class="mt-1 text-center text-ink-600">We'll email you a secure link to choose a new password.</p>
    <form method="post" action="{{ route('password.email') }}" class="card card-body mt-6 space-y-4">
        @csrf
        <x-field name="email" label="Email" type="email" required autocomplete="email" />
        <button class="btn btn-primary w-full" type="submit">Email me a reset link</button>
    </form>
    <p class="mt-6 text-center text-sm"><a href="{{ route('login') }}">Back to sign in</a></p>
</div>
@endsection
