@extends('layouts.portal', ['portal' => 'account'])
@section('title', $ticket->reference)
@section('content')
<div class="max-w-3xl">
<x-page-header :title="$ticket->subject" :subtitle="$ticket->reference.' · '.\App\Models\SupportTicket::CATEGORIES[$ticket->category]" :back="route('account.support.index')">
    <x-pill tone="info">{{ \App\Models\SupportTicket::STATUSES[$ticket->status] }}</x-pill>
</x-page-header>
@if($ticket->shipment)<p class="mb-4 text-sm">Shipment: <a href="{{ route('account.shipments.show', $ticket->shipment) }}" class="font-mono">{{ $ticket->shipment->tracking_number }}</a></p>@endif
<ol class="space-y-4">
    @foreach($ticket->publicMessages as $m)
        @php $mine = $m->user_id === auth()->id(); @endphp
        <li class="card card-body {{ $mine ? '' : 'border-brand-200 bg-brand-50/50' }}">
            <p class="text-xs font-semibold text-ink-500">{{ $mine ? 'You' : 'Support team' }} · {{ $m->created_at->diffForHumans() }}</p>
            <p class="mt-2 whitespace-pre-line text-ink-800">{{ $m->body }}</p>
        </li>
    @endforeach
</ol>
@if($ticket->status !== 'closed')
<form method="post" action="{{ route('account.support.reply', $ticket) }}" class="card card-body mt-6 space-y-3">
    @csrf
    <x-field name="body" label="Reply" type="textarea" rows="4" required />
    <button class="btn btn-primary" type="submit">Send reply</button>
</form>
@endif
</div>
@endsection
