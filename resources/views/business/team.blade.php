@extends('layouts.portal', ['portal' => 'business'])
@section('title', 'Team')
@section('content')
<x-page-header title="Team & permissions" />
<div class="grid gap-6 lg:grid-cols-[1fr_360px]">
    <div class="card"><div class="table-wrap"><table class="table">
        <thead><tr><th>Member</th><th>Role</th><th><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>@foreach($members as $m)<tr>
            <td>{{ $m->user->name }}<div class="text-xs text-ink-500">{{ $m->user->email }}</div></td>
            <td>
                @if($m->user_id === $me->user_id || ($m->role === 'owner' && $me->role !== 'owner'))
                    {{ \App\Models\BusinessMember::ROLE_LABELS[$m->role] }}
                @else
                    <form method="post" action="{{ route('business.team.update', $m) }}" class="flex gap-2">@csrf @method('PUT')
                        <label class="sr-only" for="role{{ $m->id }}">Role for {{ $m->user->name }}</label>
                        <select id="role{{ $m->id }}" name="role" class="input min-h-9 py-1">@foreach($roles as $k => $l)<option value="{{ $k }}" @selected($m->role === $k)>{{ ucfirst($k) }}</option>@endforeach</select>
                        <button class="btn btn-secondary btn-sm" type="submit">Save</button></form>
                @endif
            </td>
            <td class="text-right">@if($m->user_id !== $me->user_id && !($m->role === 'owner' && $me->role !== 'owner'))
                <form method="post" action="{{ route('business.team.destroy', $m) }}" x-data @submit="if(!confirm('Remove {{ $m->user->name }}?')) $event.preventDefault()">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-danger-700" type="submit">Remove</button></form>@endif</td>
        </tr>@endforeach</tbody>
    </table></div></div>
    <div class="space-y-4">
        <form method="post" action="{{ route('business.team.store') }}" class="card card-body space-y-4">
            @csrf
            <h2 class="text-lg">Add a team member</h2>
            <x-field name="name" label="Name" required />
            <x-field name="email" label="Email" type="email" required hint="New users get an email to set their password." />
            <x-field name="role" label="Role" type="select" required :options="$roles" />
            <button class="btn btn-primary w-full" type="submit">Add member</button>
        </form>
        <div class="card card-body text-sm"><h2 class="text-base">Roles</h2><ul class="mt-2 space-y-1 text-ink-600">@foreach(\App\Models\BusinessMember::ROLE_LABELS as $l)<li>{{ $l }}</li>@endforeach</ul></div>
    </div>
</div>
@endsection
