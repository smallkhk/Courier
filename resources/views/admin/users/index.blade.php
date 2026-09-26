@extends('layouts.portal', ['portal' => 'ops'])
@section('title', 'Users & roles')
@section('content')
<x-page-header title="Users & roles"><a href="{{ route('admin.users.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" />Add staff user</a></x-page-header>
<form method="get" class="mb-4 flex flex-wrap items-end gap-3"><x-field name="q" label="Search" :value="request('q')" /><x-field name="role" label="Role" type="select" :options="array_combine(\App\Models\User::ROLES, array_map('ucfirst', \App\Models\User::ROLES))" :value="request('role')" placeholder="Any" /><button class="btn btn-secondary" type="submit">Filter</button></form>
<div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th></th></tr></thead>
<tbody>@foreach($users as $u)<tr><td>{{ $u->name }}</td><td>{{ $u->email }}</td><td class="capitalize">{{ $u->role }}</td><td><x-pill :tone="$u->status === 'active' ? 'success' : 'danger'">{{ ucfirst($u->status) }}</x-pill></td><td>{{ $u->last_login_at?->diffForHumans() ?? '—' }}</td><td class="text-right"><a href="{{ route('admin.users.edit', $u) }}" class="btn btn-ghost btn-sm">Edit</a></td></tr>@endforeach</tbody></table></div>
<div class="border-t border-ink-200 p-4">{{ $users->links() }}</div></div>
@endsection
