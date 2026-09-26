@extends('layouts.portal', ['portal' => 'ops'])
@section('title', 'Customers')
@section('content')
<x-page-header title="Customers" />
<form method="get" class="mb-4 flex items-end gap-3" role="search"><x-field name="q" label="Search name, email, phone" :value="request('q')" /><button class="btn btn-secondary" type="submit">Search</button></form>
<div class="card">@if($users->isEmpty())<x-empty icon="users" title="No customers found" />@else
<div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Shipments</th><th>Status</th><th>Joined</th></tr></thead>
<tbody>@foreach($users as $u)<tr><td><a href="{{ route('ops.customers.show', $u) }}">{{ $u->name }}</a></td><td>{{ $u->email }}</td><td>{{ $u->phone }}</td><td>{{ $u->shipments_count }}</td>
<td><x-pill :tone="$u->status === 'active' ? 'success' : 'danger'">{{ ucfirst($u->status) }}</x-pill></td><td>{{ $u->created_at->format('j M Y') }}</td></tr>@endforeach</tbody></table></div>
<div class="border-t border-ink-200 p-4">{{ $users->links() }}</div>@endif</div>
@endsection
