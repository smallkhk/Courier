@extends('layouts.portal', ['portal' => 'ops'])
@section('title', 'Support tickets')
@section('content')
<x-page-header title="Support tickets" />
<form method="get" class="mb-4 flex flex-wrap items-end gap-3">
    <x-field name="status" label="Status" type="select" :options="['open_all' => 'All open', 'all' => 'All'] + \App\Models\SupportTicket::STATUSES" :value="request('status', 'open_all')" :placeholder="false" />
    <x-field name="category" label="Category" type="select" :options="\App\Models\SupportTicket::CATEGORIES" :value="request('category')" placeholder="Any" />
    <label class="flex items-center gap-2 pb-3 text-sm"><input type="checkbox" class="checkbox" name="mine" value="1" @checked(request('mine'))> Assigned to me</label>
    <button class="btn btn-secondary" type="submit">Filter</button>
</form>
<div class="card">@if($tickets->isEmpty())<x-empty icon="life-buoy" title="No tickets" />@else
<div class="table-wrap"><table class="table">
    <thead><tr><th>Ref</th><th>Subject</th><th>Customer</th><th>Category</th><th>Priority</th><th>Status</th><th>Assignee</th><th>Updated</th></tr></thead>
    <tbody>@foreach($tickets as $t)<tr>
        <td><a href="{{ route('ops.support.show', $t) }}" class="font-mono">{{ $t->reference }}</a></td><td>{{ \Illuminate\Support\Str::limit($t->subject, 50) }}</td>
        <td>{{ $t->contact_name }}</td><td>{{ \App\Models\SupportTicket::CATEGORIES[$t->category] }}</td>
        <td><x-pill :tone="['urgent'=>'danger','high'=>'warning'][$t->priority] ?? 'neutral'">{{ ucfirst($t->priority) }}</x-pill></td>
        <td>{{ \App\Models\SupportTicket::STATUSES[$t->status] }}</td><td>{{ $t->assignee?->name ?? '—' }}</td><td>{{ $t->updated_at->diffForHumans() }}</td>
    </tr>@endforeach</tbody>
</table></div><div class="border-t border-ink-200 p-4">{{ $tickets->links() }}</div>@endif</div>
@endsection
