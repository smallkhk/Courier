@extends('layouts.public')
@section('title', 'Sign in')
@section('content')
<div class="mx-auto max-w-md">
    <div class="mb-6 text-center">
        <span class="mx-auto grid size-12 place-items-center rounded-xl bg-brand-600 text-white"><x-icon name="user" /></span>
        <h1 class="mt-4 text-2xl">Sign in</h1>
        <p class="mt-1 text-ink-600">Customers, businesses, riders and staff.</p>
    </div>
    <form method="post" action="{{ route('login') }}" class="card card-body space-y-4">
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
</div>
@endsection
