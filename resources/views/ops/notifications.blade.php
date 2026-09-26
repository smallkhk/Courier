@extends('layouts.portal', ['portal' => 'ops'])
@section('title', 'Notifications log')
@section('content')
<x-page-header title="Notifications log" :subtitle="'Email driver: '.$drivers['email'].' · SMS driver: '.$drivers['sms'].($drivers['sms'] === 'log' || $drivers['email'] === 'log' ? ' (log = development, nothing is actually sent)' : '')" />
<div class="mb-4 flex flex-wrap gap-2">@foreach(['queued','sending','sent','failed','skipped'] as $st)<a href="{{ route('ops.notifications', ['status' => $st]) }}" class="badge {{ request('status') === $st ? 'badge-info' : 'badge-neutral' }} px-3 py-1 text-sm no-underline">{{ ucfirst($st) }}: {{ $counts[$st] ?? 0 }}</a>@endforeach<a href="{{ route('ops.notifications') }}" class="text-sm">All</a></div>
<div class="card">@if($logs->isEmpty())<x-empty icon="bell" title="No notifications" />@else
<div class="table-wrap"><table class="table"><thead><tr><th>Event</th><th>Channel</th><th>To</th><th>Shipment</th><th>Status</th><th>Attempts</th><th>Provider</th><th>Created</th><th><span class="sr-only">Actions</span></th></tr></thead>
<tbody>@foreach($logs as $n)<tr>
    <td>{{ \App\Services\Notifications\NotificationService::EVENTS[$n->event] ?? $n->event }}</td><td>{{ strtoupper($n->channel) }}</td>
    <td class="font-mono text-xs">{{ $n->maskedRecipient() }}</td>
    <td>@if($n->shipment)<a href="{{ route('ops.shipments.show', $n->shipment) }}" class="font-mono text-xs">{{ $n->shipment->tracking_number }}</a>@endif</td>
    <td><x-pill :tone="['sent'=>'success','failed'=>'danger','queued'=>'warning','sending'=>'warning','skipped'=>'neutral'][$n->status]">{{ ucfirst($n->status) }}</x-pill>@if($n->last_error)<div class="max-w-56 truncate text-xs text-danger-700" title="{{ $n->last_error }}">{{ $n->last_error }}</div>@endif</td>
    <td>{{ $n->attempts }}</td><td class="text-xs">{{ $n->provider }} {{ $n->provider_message_id ? '· '.\Illuminate\Support\Str::limit($n->provider_message_id, 12) : '' }}</td>
    <td class="whitespace-nowrap text-xs">{{ $n->created_at->format('j M H:i') }}</td>
    <td>@if(in_array($n->status, ['failed','skipped']))<form method="post" action="{{ route('ops.notifications.retry', $n) }}">@csrf<button class="btn btn-ghost btn-sm" type="submit">Retry</button></form>@endif</td>
</tr>@endforeach</tbody></table></div><div class="border-t border-ink-200 p-4">{{ $logs->links() }}</div>@endif</div>
@endsection
