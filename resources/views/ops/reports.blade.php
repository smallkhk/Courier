@extends('layouts.portal', ['portal' => 'ops'])
@section('title', 'Reports')
@section('content')
<x-page-header title="Reports & analytics"><a href="{{ route('ops.reports.export', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="btn btn-secondary"><x-icon name="download" class="size-4" />Export CSV</a></x-page-header>
@include('shared.report-body', ['summary' => $summary, 'from' => $from, 'to' => $to])
<div class="card mt-6"><div class="card-header"><h2 class="text-base">Rider workload ({{ $from->format('j M') }} – {{ $to->format('j M') }})</h2></div>
<div class="table-wrap"><table class="table"><thead><tr><th>Rider</th><th>Open now</th><th>Jobs completed</th><th>Failed attempts</th></tr></thead>
<tbody>@forelse($riders as $r)<tr><td>{{ $r['name'] }}</td><td>{{ $r['open'] }}</td><td>{{ $r['completed'] }}</td><td>{{ $r['failed'] }}</td></tr>@empty<tr><td colspan="4" class="text-ink-500">No riders.</td></tr>@endforelse</tbody></table></div></div>
@endsection
