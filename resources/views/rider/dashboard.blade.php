@extends('layouts.portal', ['portal' => 'rider'])
@section('title', 'Rider')
@section('content')
<div class="mx-auto max-w-3xl space-y-5">
    <div class="card card-body flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="text-sm text-ink-500">Duty status</p>
            <p class="flex items-center gap-2 text-xl font-bold">
                <span class="size-3 rounded-full {{ $profile->on_duty ? 'bg-success-600' : 'bg-ink-300' }}" aria-hidden="true"></span>
                {{ $profile->on_duty ? 'On duty' : 'Off duty' }}
            </p>
            @if($profile->on_duty)<p class="text-xs text-ink-500">Since {{ $profile->on_duty_since?->format('g:i a') }}</p>@endif
        </div>
        <form method="post" action="{{ route('rider.duty') }}">@csrf
            <input type="hidden" name="on_duty" value="{{ $profile->on_duty ? 0 : 1 }}">
            <button class="btn {{ $profile->on_duty ? 'btn-secondary' : 'btn-primary' }} btn-lg" type="submit">{{ $profile->on_duty ? 'Go off duty' : 'Go on duty' }}</button>
        </form>
    </div>

    @if($profile->on_duty)
    <section class="card card-body" x-data="locationSharing(@js($locCfg))" aria-labelledby="loc-h">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="loc-h" class="flex items-center gap-2 text-base"><x-icon name="navigation" class="size-4" />Location sharing</h2>
                <p class="mt-1 text-sm" role="status" aria-live="polite">
                    <template x-if="state === 'active'"><span class="badge badge-success"><span class="size-2 animate-pulse rounded-full bg-success-600"></span>Sharing — last sent <span x-text="sinceText()"></span><span x-show="lastAccuracy">, ±<span x-text="lastAccuracy"></span> m</span></span></template>
                    <template x-if="state === 'starting'"><span class="badge badge-info">Waiting for GPS…</span></template>
                    <template x-if="state === 'off'"><span class="badge badge-neutral">Not sharing</span></template>
                    <template x-if="state === 'denied'"><span class="badge badge-danger">Permission denied</span></template>
                    <template x-if="state === 'unavailable' || state === 'error'"><span class="badge badge-warning">Location unavailable</span></template>
                </p>
                <p class="mt-2 text-sm text-warning-800" x-show="message" x-text="message"></p>
            </div>
            <div>
                <button type="button" class="btn btn-primary" x-show="!sharing" @click="enable()">Start sharing</button>
                <button type="button" class="btn btn-danger" x-show="sharing" x-cloak @click="disable()">Stop sharing</button>
            </div>
        </div>
        <details class="mt-3 text-sm text-ink-600" @if(! $profile->location_consent_at) open @endif>
            <summary class="cursor-pointer font-medium text-ink-800">Why we ask for your location</summary>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>Operations uses it to assign nearby jobs and answer customer questions.</li>
                <li>It is only sent while you are <strong>on duty</strong>, sharing is <strong>on</strong>, and this page is open.</li>
                <li>Customers never see your exact position — at most an area within about 1 km while their parcel is out for delivery.</li>
                <li>Location history is deleted automatically after {{ \App\Support\Settings::get('location_retention_days') }} days.</li>
                <li>Your phone may pause updates when the screen is locked or the browser is in the background.</li>
            </ul>
            <p class="mt-2">Pressing “Start sharing” records your consent. You can stop at any time.</p>
        </details>
    </section>
    @endif

    <div class="grid grid-cols-2 gap-4">
        <x-stat label="Open jobs" :value="$jobs->count()" icon="package" />
        <x-stat label="Completed today" :value="$doneToday" icon="check-circle" />
    </div>

    <section aria-labelledby="jobs-h">
        <h2 id="jobs-h" class="mb-3 text-lg">Your jobs</h2>
        @forelse($jobs as $a)
            @php $s = $a->shipment; @endphp
            <a href="{{ route('rider.jobs.show', $a) }}" class="card mb-3 block p-4 no-underline transition hover:border-brand-300 hover:no-underline">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-mono font-semibold text-ink-900">{{ $s->tracking_number }}</span>
                    <span class="flex gap-2">
                        <x-pill :tone="$a->leg === 'pickup' ? 'accent' : 'info'">{{ ucfirst($a->leg) }}</x-pill>
                        <x-status-badge :status="$s->status" />
                    </span>
                </div>
                <p class="mt-2 text-sm text-ink-800">
                    @if($a->leg === 'pickup')<x-icon name="map-pin" class="inline size-4 text-ink-400" /> {{ $s->addressLine('pickup') }}
                    @else<x-icon name="navigation" class="inline size-4 text-ink-400" /> {{ $s->addressLine('delivery') }}@endif
                </p>
                <p class="mt-1 text-xs text-ink-500">{{ $s->parcel_count }} parcel(s) · {{ $s->service->name }} @if($a->status === 'assigned')· <strong class="text-accent-700">New — tap to accept</strong>@endif</p>
            </a>
        @empty
            <div class="card"><x-empty icon="check-circle" title="No open jobs">{{ $profile->on_duty ? 'New assignments will appear here.' : 'Go on duty to receive assignments.' }}</x-empty></div>
        @endforelse
    </section>
</div>
@endsection
