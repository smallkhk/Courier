@extends('layouts.public')
@section('title', 'Sign in')
@section('bare', true)
@section('content')
<x-auth-shell title="Welcome back" subtitle="Sign in to book, track and manage deliveries. Customers, businesses, riders and staff." image="courier-boxes">
    <form method="post" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <x-field name="email" label="Email" type="email" required autocomplete="email" autofocus />
        <x-field name="password" label="Password" type="password" required autocomplete="current-password" />
        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" name="remember" value="1" class="checkbox"> Keep me signed in</label>
            <a href="{{ route('password.request') }}">Forgot password?</a>
        </div>
        <button class="btn btn-primary btn-lg w-full" type="submit">Sign in</button>
    </form>
    <p class="mt-6 text-center text-sm text-ink-600">New here? <a href="{{ route('register') }}">Create an account</a></p>
</x-auth-shell>
@endsection
