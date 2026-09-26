@extends('layouts.portal', ['portal' => 'ops'])
@section('title', 'Riders')
@section('content')
<x-page-header title="Riders">@if(auth()->user()->isRole('admin'))<a href="{{ route('ops.riders.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" />Add rider</a>@endif</x-page-header>
<div class="card">@if($riders->isEmpty())<x-empty icon="bike" title="No riders yet" />@else
<div class="table-wrap"><table class="table"><thead><tr><th>Rider</th><th>Zone</th><th>Vehicle</th><th>Duty</th><th>Location</th><th>Open jobs</th><th>Account</th></tr></thead>
<tbody>@foreach($riders as $r)@php $f = $r->locationFreshness(); @endphp<tr>
    <td><a href="{{ route('ops.riders.show', $r->user) }}">{{ $r->user->name }}</a><div class="text-xs text-ink-500">{{ $r->user->phone }}</div></td>
    <td>{{ $r->zone?->name ?? '—' }}</td><td>{{ $r->vehicle_type }} {{ $r->plate_number }}</td>
    <td><x-pill :tone="$r->on_duty ? 'success' : 'neutral'">{{ $r->on_duty ? 'On duty' : 'Off duty' }}</x-pill></td>
    <td class="text-xs">@if($f === 'live')<span class="text-success-700">Live</span>@elseif($f === 'stale')Last seen {{ $r->last_location_at->diffForHumans() }}@else Never shared @endif</td>
    <td>{{ $r->activeAssignmentCount() }} / {{ $r->max_active_assignments }}</td>
    <td><x-pill :tone="$r->is_active ? 'success' : 'danger'">{{ $r->is_active ? 'Active' : 'Inactive' }}</x-pill></td>
</tr>@endforeach</tbody></table></div>@endif</div>
@endsection
