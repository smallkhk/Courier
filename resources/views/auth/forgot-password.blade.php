@extends('layouts.public')
@section('title', 'Reset password')
@section('bare', true)
@section('content')
<x-auth-shell title="Reset your password" subtitle="We'll email you a secure link to choose a new password." image="warehouse-aisle">
    <form method="post" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <x-field name="email" label="Email" type="email" required autocomplete="email" />
        <button class="btn btn-primary w-full" type="submit">Email me a reset link</button>
    </form>
    <p class="mt-6 text-center text-sm"><a href="{{ route('login') }}">Back to sign in</a></p>
</x-auth-shell>
@endsection
