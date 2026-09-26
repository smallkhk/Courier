@extends('layouts.portal', ['portal' => 'ops'])
@section('title', $u->exists ? $u->name : 'Add user')
@section('content')
<div class="max-w-xl">
<x-page-header :title="$u->exists ? $u->name : 'Add staff user'" :subtitle="$u->email" :back="route('admin.users.index')" />
<form method="post" action="{{ $u->exists ? route('admin.users.update', $u) : route('admin.users.store') }}" class="card card-body space-y-4">
    @csrf @if($u->exists) @method('PUT') @endif
    <x-field name="name" label="Name" required :value="$u->name" />
    @unless($u->exists)<x-field name="email" label="Email" type="email" required hint="They'll get an email to set their password." />@endunless
    <x-field name="phone" label="Phone" :value="$u->phone" />
    <x-field name="role" label="Role" type="select" required :options="$u->exists ? array_combine(\App\Models\User::ROLES, array_map('ucfirst', \App\Models\User::ROLES)) : ['dispatcher' => 'Dispatcher', 'admin' => 'Admin', 'customer' => 'Customer']" :value="$u->role" :placeholder="false" hint="Dispatchers manage shipments and riders; admins also manage configuration, refunds and users." />
    @if($u->exists)<x-field name="status" label="Status" type="select" :options="['active' => 'Active', 'suspended' => 'Suspended (signs out everywhere)']" :value="$u->status" :placeholder="false" />@endif
    <button class="btn btn-primary" type="submit">Save</button>
</form>
</div>
@endsection
