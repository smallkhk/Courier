@extends('layouts.portal', ['portal' => 'account'])
@section('title', 'Profile & settings')
@section('content')
<x-page-header title="Profile & settings" />
<div class="grid gap-6 lg:grid-cols-2">
    <form method="post" action="{{ route('account.profile.update') }}" class="card card-body space-y-4">
        @csrf @method('PUT')
        <h2 class="text-lg">Your details</h2>
        <x-field name="name" label="Full name" required :value="$user->name" autocomplete="name" />
        <x-field name="email" label="Email" type="email" required :value="$user->email" autocomplete="email" :hint="$user->hasVerifiedEmail() ? 'Verified' : 'Not verified yet'" />
        <x-field name="phone" label="Phone" type="tel" required :value="$user->phone" autocomplete="tel" />
        <fieldset class="space-y-2">
            <legend class="label">Shipment notifications</legend>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" name="notify_email" value="1" @checked($user->wantsNotification('email'))> Email updates</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" name="notify_sms" value="1" @checked($user->wantsNotification('sms'))> SMS updates (I consent to receive SMS)</label>
        </fieldset>
        <button class="btn btn-primary" type="submit">Save changes</button>
    </form>
    <form method="post" action="{{ route('account.profile.password') }}" class="card card-body h-fit space-y-4">
        @csrf @method('PUT')
        <h2 class="text-lg">Change password</h2>
        <x-field name="current_password" label="Current password" type="password" required autocomplete="current-password" />
        <x-field name="password" label="New password" type="password" required autocomplete="new-password" hint="At least 10 characters, with letters and numbers." />
        <x-field name="password_confirmation" label="Confirm new password" type="password" required autocomplete="new-password" />
        <button class="btn btn-secondary" type="submit">Update password</button>
    </form>
</div>
@endsection
