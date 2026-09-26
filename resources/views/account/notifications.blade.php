@extends('layouts.portal', ['portal' => 'account'])
@section('title', 'Notifications')
@section('content')
<x-page-header title="Notifications" subtitle="Messages we've sent you about your shipments."><a href="{{ route('account.profile') }}" class="btn btn-secondary">Notification preferences</a></x-page-header>
<div class="card">
    @if($logs->isEmpty())<x-empty icon="bell" title="No notifications yet" />@else
    <ul class="divide-y divide-ink-100">
        @foreach($logs as $n)
            <li class="flex flex-wrap items-start justify-between gap-3 p-4">
                <div class="flex gap-3">
                    <x-icon :name="$n->channel === 'sms' ? 'phone' : 'mail'" class="mt-0.5 size-5 text-ink-400" />
                    <div><p class="font-medium">{{ $n->subject ?? \App\Services\Notifications\NotificationService::EVENTS[$n->event] ?? $n->event }}</p>
                        <p class="text-xs text-ink-500">{{ strtoupper($n->channel) }} · {{ $n->created_at->diffForHumans() }} @if($n->shipment) · <a href="{{ route('account.shipments.show', $n->shipment) }}">{{ $n->shipment->tracking_number }}</a>@endif</p></div>
                </div>
                <x-pill :tone="['sent' => 'success', 'failed' => 'danger', 'queued' => 'warning', 'sending' => 'warning', 'skipped' => 'neutral'][$n->status]">{{ ucfirst($n->status) }}</x-pill>
            </li>
        @endforeach
    </ul>
    <div class="border-t border-ink-200 p-4">{{ $logs->links() }}</div>@endif
</div>
@endsection
