@extends('layouts.portal', ['portal' => 'ops'])
@section('title', 'Rider map')
@section('content')
<x-page-header title="Rider locations" subtitle="Last known positions from riders who are on duty and have chosen to share location." />
<div x-data="riderMap(@js(route('ops.map.data')), @js($cfg))" class="grid gap-6 xl:grid-cols-[1fr_320px]">
    <div>
        <div x-ref="map" class="h-[60vh] min-h-96 rounded-[var(--radius-md)] border border-ink-200 bg-ink-100" role="img" aria-label="Map of rider locations. The same data is listed alongside."></div>
        <p class="mt-2 text-xs text-ink-500">Green = updated within {{ $staleMinutes }} min (live). Grey = older (last known, not live). Circles show reported GPS accuracy. Refreshes every 30 s<span x-show="updatedAt"> — last refresh <span x-text="updatedAt"></span></span>.</p>
        <p class="mt-1 text-sm text-danger-700" x-show="error" x-text="error"></p>
    </div>
    <ul class="space-y-2" aria-label="Riders">
        <template x-for="r in riders" :key="r.id">
            <li class="card p-3 text-sm">
                <div class="flex justify-between gap-2"><span class="font-semibold" x-text="r.name"></span>
                    <span class="badge" :class="r.freshness === 'live' ? 'badge-success' : (r.freshness === 'stale' ? 'badge-neutral' : 'badge-warning')" x-text="r.freshness === 'live' ? 'Live' : (r.freshness === 'stale' ? 'Last known' : 'No location')"></span></div>
                <p class="text-xs text-ink-500"><span x-text="r.on_duty ? 'On duty' : 'Off duty'"></span> · <span x-text="r.open_jobs"></span> jobs · seen <span x-text="r.last_seen_human"></span><span x-show="r.accuracy_m"> · ±<span x-text="Math.round(r.accuracy_m)"></span> m</span></p>
            </li>
        </template>
        <li x-show="riders.length === 0" class="text-sm text-ink-500">No active riders.</li>
    </ul>
</div>
@endsection
