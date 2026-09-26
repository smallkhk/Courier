@extends('layouts.portal', ['portal' => 'ops'])
@section('title', 'Audit log')
@section('content')
<x-page-header title="Audit log" subtitle="Administrative, financial and sensitive actions. Secrets and passwords are never recorded." />
<form method="get" class="card card-body mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-6 lg:items-end">
    <x-field name="action" label="Action starts with" :value="request('action')" placeholder="e.g. payment." />
    <x-field name="actor" label="Actor" :value="request('actor')" />
    <x-field name="entity" label="Entity" type="select" :options="$entities->mapWithKeys(fn($e) => [$e => $e])" :value="request('entity')" placeholder="Any" />
    <x-field name="from" label="From" type="date" :value="request('from')" /><x-field name="to" label="To" type="date" :value="request('to')" />
    <button class="btn btn-secondary" type="submit">Search</button>
</form>
<div class="card">@if($logs->isEmpty())<x-empty icon="history" title="No entries" />@else
<div class="table-wrap"><table class="table"><thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Entity</th><th>Details</th><th>IP</th></tr></thead>
<tbody>@foreach($logs as $l)<tr>
    <td class="whitespace-nowrap text-xs">{{ \Illuminate\Support\Carbon::parse($l->created_at)->format('j M Y H:i:s') }}</td>
    <td>{{ $l->actor?->name ?? 'system/guest' }}</td><td class="font-mono text-xs">{{ $l->action }}</td>
    <td class="text-xs">{{ $l->entity_type }} {{ $l->entity_id }}</td>
    <td><details class="text-xs"><summary class="cursor-pointer">view</summary><pre class="mt-1 max-w-md overflow-x-auto rounded bg-ink-50 p-2">{{ json_encode($l->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details></td>
    <td class="text-xs">{{ $l->ip }}</td>
</tr>@endforeach</tbody></table></div><div class="border-t border-ink-200 p-4">{{ $logs->links() }}</div>@endif</div>
@endsection
