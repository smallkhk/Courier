@extends('layouts.public')
@section('title', 'Choose a new password')
@section('bare', true)
@section('content')
<x-auth-shell title="Choose a new password" image="warehouse-aisle">
    <form method="post" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-field name="email" label="Email" type="email" required :value="$email" autocomplete="email" />
        <x-field name="password" label="New password" type="password" required autocomplete="new-password" hint="At least 10 characters, with letters and numbers." />
        <x-field name="password_confirmation" label="Confirm new password" type="password" required autocomplete="new-password" />
        <button class="btn btn-primary w-full" type="submit">Reset password</button>
    </form>
</x-auth-shell>
@endsection
