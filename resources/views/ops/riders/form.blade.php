@extends('layouts.portal', ['portal' => 'ops'])
@section('title', $profile ? $profile->user->name : 'Add rider')
@section('content')
@php $admin = auth()->user()->isRole('admin'); @endphp
<x-page-header :title="$profile ? $profile->user->name : 'Add rider'" :subtitle="$profile?->user->email" :back="route('ops.riders.index')" />
<div class="grid gap-6 lg:grid-cols-[420px_1fr]">
    <form method="post" action="{{ $profile ? route('ops.riders.update', $profile->user) : route('ops.riders.store') }}" class="card card-body h-fit space-y-4">
        @csrf @if($profile) @method('PUT') @endif
        @unless($profile)
            <x-field name="name" label="Full name" required />
            <x-field name="email" label="Email" type="email" required hint="They'll receive a link to set their password." />
        @endunless
        <x-field name="phone" label="Phone" required :value="$profile?->user->phone" />
        <x-field name="service_zone_id" label="Home zone" type="select" :options="$zones" :value="$profile?->service_zone_id" placeholder="None" />
        <div class="grid grid-cols-2 gap-3">
            <x-field name="vehicle_type" label="Vehicle" type="select" :options="['motorcycle'=>'Motorcycle','bicycle'=>'Bicycle','car'=>'Car','van'=>'Van','truck'=>'Truck','foot'=>'On foot']" :value="$profile?->vehicle_type ?? 'motorcycle'" :placeholder="false" />
            <x-field name="plate_number" label="Plate number" :value="$profile?->plate_number" />
        </div>
        <x-field name="max_active_assignments" label="Max open jobs" type="number" min="1" max="100" required :value="$profile?->max_active_assignments ?? 15" />
        @if($profile)<label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" name="is_active" value="1" @checked($profile->is_active)> Active (can receive jobs)</label>@endif
        @if($admin)<button class="btn btn-primary w-full" type="submit">{{ $profile ? 'Save' : 'Create rider' }}</button>@else<p class="text-sm text-ink-500">Only administrators can edit riders.</p>@endif
    </form>
    @if($profile)
    <div class="card"><div class="card-header"><h2 class="text-base">Recent jobs</h2><span class="text-sm text-ink-500">{{ $profile->on_duty ? 'On duty' : 'Off duty' }} · location: {{ $profile->locationFreshness() }}</span></div>
        @if($jobs->isEmpty())<p class="p-6 text-sm text-ink-500">No jobs yet.</p>@else
        <div class="table-wrap"><table class="table"><thead><tr><th>Shipment</th><th>Leg</th><th>Status</th><th>Assigned</th></tr></thead>
        <tbody>@foreach($jobs as $a)<tr><td><a href="{{ route('ops.shipments.show', $a->shipment) }}" class="font-mono">{{ $a->shipment->tracking_number }}</a></td><td>{{ $a->leg }}</td><td>{{ $a->status }}</td><td>{{ $a->assigned_at->format('j M H:i') }}</td></tr>@endforeach</tbody></table></div>@endif
    </div>
    @endif
</div>
@endsection
