@extends('layouts.portal', ['portal' => 'ops'])
@section('title', 'Software updates')
@section('content')
<x-page-header title="Software updates" subtitle="Install new versions of your site with one click. Your data, settings and uploads are never touched." />

@if($pending)
    <x-alert type="warning" class="mb-6" title="An update is finishing">Version {{ $pending['to'] }} was installed and is finishing its database step. This completes automatically within a minute (via the scheduled task); refresh this page shortly.</x-alert>
@endif

<div class="grid gap-6 lg:grid-cols-3">
    <section class="card lg:col-span-2" data-reveal>
        <div class="card-header"><h2 class="text-base">Your version</h2>
            <form method="post" action="{{ route('admin.updates.check') }}">@csrf<button class="btn btn-secondary btn-sm" type="submit"><x-icon name="undo" class="size-4" />Check now</button></form>
        </div>
        <div class="card-body">
            <div class="flex flex-wrap items-center gap-6">
                <div><p class="stat-label">Installed</p><p class="text-3xl font-bold tabular-nums">{{ $current }}</p></div>
                @if($latest)
                    <x-icon name="arrow-right" class="size-6 text-ink-300" />
                    <div><p class="stat-label">Latest</p><p class="text-3xl font-bold tabular-nums {{ $available ? 'text-brand-700' : '' }}">{{ $latest['version'] }}</p></div>
                @endif
            </div>
            @if($error)<x-alert type="warning" class="mt-5">{{ $error }}</x-alert>@endif

            @if($available)
                <div class="mt-6 rounded-xl border border-brand-200 bg-brand-50/70 p-5">
                    <p class="flex items-center gap-2 font-semibold text-brand-900"><span class="relative flex size-2.5"><span class="absolute inline-flex size-full animate-ping rounded-full bg-brand-500 opacity-60"></span><span class="relative inline-flex size-2.5 rounded-full bg-brand-600"></span></span>{{ $latest['name'] }} is ready to install</p>
                    @if($latest['published_at'])<p class="mt-1 text-xs text-ink-500">Published {{ \Illuminate\Support\Carbon::parse($latest['published_at'])->diffForHumans() }}@if($latest['size']) · {{ number_format($latest['size'] / 1048576, 1) }} MB @endif</p>@endif
                    @if(trim($latest['notes']))
                        <div class="prose-simple mt-4 max-h-64 overflow-y-auto rounded-lg bg-white p-4 text-sm">{!! \Illuminate\Support\Str::markdown($latest['notes'], ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                    @endif
                    <form method="post" action="{{ route('admin.updates.install') }}" class="mt-5 grid gap-3 sm:flex sm:items-end" x-data="{ busy: false }" @submit="busy = true">
                        @csrf
                        <div class="sm:w-72">
                            <label class="label" for="current_password">Your password, to confirm</label>
                            <input id="current_password" name="current_password" type="password" class="input" autocomplete="current-password" required>
                        </div>
                        <button class="btn btn-primary btn-lg btn-shine" type="submit" :disabled="busy">
                            <span x-show="!busy"><x-icon name="download" class="inline size-4" /> Install update</span>
                            <span x-show="busy" x-cloak class="inline-flex items-center gap-2"><span class="spinner"></span>Installing — keep this page open…</span>
                        </button>
                    </form>
                    <p class="mt-3 text-xs text-ink-600">The site shows a short “back soon” page for about a minute. Changed files are backed up first{{ collect($requirements)->firstWhere(0, 'Database backup (mysqldump)')[1] ? ' together with the database' : '' }}, and anything that fails is put back automatically.</p>
                </div>
            @elseif($latest)
                <x-alert type="success" class="mt-6">You're up to date.</x-alert>
            @elseif(! $error)
                <p class="mt-6 text-sm text-ink-600">No releases have been published yet.</p>
            @endif
        </div>
    </section>

    <section class="card" data-reveal>
        <div class="card-header"><h2 class="text-base">Server checks</h2></div>
        <ul class="divide-y divide-ink-100">
            @foreach($requirements as $req)
                @php [$label, $ok, $help] = $req; $optional = $req[3] ?? false; @endphp
                <li class="flex gap-3 px-4 py-3 text-sm sm:px-6">
                    <x-icon :name="$ok ? 'check-circle' : 'alert'" class="mt-0.5 size-5 {{ $ok ? 'text-success-600' : ($optional ? 'text-warning-600' : 'text-danger-600') }}" />
                    <div><p class="font-medium text-ink-900">{{ $label }}</p>@unless($ok)<p class="text-xs text-ink-600">{{ $help }}</p>@endunless</div>
                </li>
            @endforeach
        </ul>
    </section>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <section class="card" data-reveal>
        <div class="card-header"><h2 class="text-base">Restore a previous version</h2></div>
        @if(empty($backups))
            <x-empty icon="history" title="No backups yet">A backup is taken automatically before every update.</x-empty>
        @else
            <ul class="divide-y divide-ink-100">
                @foreach($backups as $b)
                    <li class="px-4 py-3 sm:px-6" x-data="{ open: false }">
                        <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                            <span>Before updating <strong>{{ $b['from'] }} → {{ $b['to'] }}</strong><span class="block text-xs text-ink-500">{{ \Illuminate\Support\Carbon::parse($b['created_at'])->diffForHumans() }}{{ $b['database'] ? ' · includes database backup' : '' }}</span></span>
                            <button type="button" class="btn btn-secondary btn-sm" @click="open = !open">Restore {{ $b['from'] }}</button>
                        </div>
                        <form x-show="open" x-cloak method="post" action="{{ route('admin.updates.rollback') }}" class="mt-3 grid gap-2 sm:flex sm:items-end">
                            @csrf<input type="hidden" name="backup" value="{{ $b['id'] }}">
                            <div class="sm:w-64"><label class="label" for="pw-{{ $loop->index }}">Your password</label><input id="pw-{{ $loop->index }}" name="current_password" type="password" class="input" autocomplete="current-password" required></div>
                            <button class="btn btn-danger" type="submit">Restore files</button>
                        </form>
                    </li>
                @endforeach
            </ul>
            <p class="border-t border-ink-100 px-4 py-3 text-xs text-ink-500 sm:px-6">Restoring puts back the program files only; your shipments, customers and settings stay as they are. Backups are kept in <code>storage/app/updates/backups</code> (the last three).</p>
        @endif
    </section>

    <section class="card" data-reveal>
        <div class="card-header"><h2 class="text-base">History</h2></div>
        @if($history->isEmpty())
            <x-empty icon="history" title="No updates yet" />
        @else
            <ul class="divide-y divide-ink-100">
                @foreach($history as $h)
                    <li class="flex items-start justify-between gap-3 px-4 py-3 text-sm sm:px-6">
                        <div>
                            <p class="font-medium text-ink-900">{{ $h->action === 'rollback' ? 'Restored' : 'Updated' }} {{ $h->from_version }} → {{ $h->to_version }}</p>
                            <p class="text-xs text-ink-500">{{ $h->created_at->diffForHumans() }}{{ $h->user ? ' by '.$h->user->name : ' (automatic)' }}</p>
                            @if($h->status === 'failed')<p class="mt-1 text-xs text-danger-700">{{ \Illuminate\Support\Str::limit($h->log, 240) }}</p>@endif
                        </div>
                        <span class="badge {{ $h->status === 'completed' ? 'badge-success' : 'badge-danger' }}">{{ ucfirst($h->status) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
@endsection
