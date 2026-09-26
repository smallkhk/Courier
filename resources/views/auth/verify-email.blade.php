@extends('layouts.public')
@section('title', 'Verify your email')
@section('content')
<div class="mx-auto max-w-md text-center">
    <span class="mx-auto grid size-14 place-items-center rounded-full bg-brand-50 text-brand-600"><x-icon name="mail" class="size-7" /></span>
    <h1 class="mt-4 text-2xl">Check your inbox</h1>
    <p class="mt-2 text-ink-600">We sent a verification link to <strong>{{ auth()->user()->email }}</strong>. Click it to verify your email address.</p>
    <form method="post" action="{{ route('verification.send') }}" class="mt-6">@csrf<button class="btn btn-secondary" type="submit">Resend the link</button></form>
</div>
@endsection
