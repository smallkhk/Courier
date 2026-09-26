@extends('layouts.portal', ['portal' => 'ops'])
@section('title', 'Businesses')
@section('content')
<x-page-header title="Business accounts" />
<form method="get" class="mb-4 flex items-end gap-3"><x-field name="status" label="Status" type="select" :options="['pending'=>'Pending review','approved'=>'Approved','suspended'=>'Suspended','rejected'=>'Rejected']" :value="request('status')" placeholder="Any" /><button class="btn btn-secondary" type="submit">Filter</button></form>
<div class="card">@if($businesses->isEmpty())<x-empty icon="building" title="No business accounts" />@else
<div class="table-wrap"><table class="table"><thead><tr><th>Business</th><th>Contact</th><th>Terms</th><th>Members</th><th>Shipments</th><th>Status</th><th>Registered</th></tr></thead>
<tbody>@foreach($businesses as $b)<tr><td><a href="{{ route('ops.businesses.show', $b) }}">{{ $b->name }}</a></td><td>{{ $b->email }}</td><td>{{ ucfirst($b->payment_terms) }}</td><td>{{ $b->memberships_count }}</td><td>{{ $b->shipments_count }}</td>
<td><x-pill :tone="['approved'=>'success','pending'=>'warning'][$b->status] ?? 'danger'">{{ ucfirst($b->status) }}</x-pill></td><td>{{ $b->created_at->format('j M Y') }}</td></tr>@endforeach</tbody></table></div>
<div class="border-t border-ink-200 p-4">{{ $businesses->links() }}</div>@endif</div>
@endsection
