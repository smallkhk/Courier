@extends('layouts.portal', ['portal' => 'account'])
@section('title', 'Support')
@section('content')
<x-page-header title="Support requests"><a href="{{ route('account.support.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" />New request</a></x-page-header>
<div class="card">
    @if($tickets->isEmpty())<x-empty icon="life-buoy" title="No support requests" action="Open a request" :action-url="route('account.support.create')">Report a delay, damage or payment issue.</x-empty>@else
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Reference</th><th>Subject</th><th>Category</th><th>Status</th><th>Updated</th></tr></thead>
        <tbody>@foreach($tickets as $t)<tr>
            <td><a href="{{ route('account.support.show', $t) }}" class="font-mono">{{ $t->reference }}</a></td><td>{{ $t->subject }}</td>
            <td>{{ \App\Models\SupportTicket::CATEGORIES[$t->category] }}</td>
            <td><x-pill :tone="in_array($t->status, ['resolved','closed']) ? 'success' : ($t->status === 'awaiting_customer' ? 'warning' : 'info')">{{ \App\Models\SupportTicket::STATUSES[$t->status] }}</x-pill></td>
            <td>{{ $t->updated_at->diffForHumans() }}</td></tr>@endforeach</tbody>
    </table></div><div class="border-t border-ink-200 p-4">{{ $tickets->links() }}</div>@endif
</div>
@endsection
