@extends('layouts.portal', ['portal' => 'ops'])
@section('title', 'System settings')
@section('content')
<x-page-header title="System settings" />
@foreach($problems as [$level, $msg])<x-alert :type="$level === 'error' ? 'danger' : 'warning'" class="mb-2">{{ $msg }}</x-alert>@endforeach
<div class="grid gap-6 lg:grid-cols-[1fr_360px]">
    <form method="post" action="{{ route('admin.settings.update') }}" class="card card-body grid gap-4 sm:grid-cols-2">
        @csrf @method('PUT')
        @foreach(\App\Support\Settings::EDITABLE as $key => [$type, $label])
            @php $v = $values[$key] ?? null; @endphp
            @if($type === 'bool')
                <label class="flex items-start gap-2 text-sm sm:col-span-2"><input type="checkbox" class="checkbox mt-0.5" name="{{ $key }}" value="1" @checked(old($key, $v))> {{ $label }}</label>
            @elseif($type === 'list')
                <x-field :name="$key" :label="$label" :value="is_array($v) ? implode(', ', $v) : $v" />
            @else
                <x-field :name="$key" :label="$label" :type="$type === 'int' ? 'number' : ($type === 'email' ? 'email' : 'text')" :value="$v" />
            @endif
        @endforeach
        <div class="sm:col-span-2"><button class="btn btn-primary" type="submit">Save settings</button></div>
    </form>
    <aside class="space-y-4">
        <div class="card card-body text-sm">
            <h2 class="text-base">Integration status</h2>
            <dl class="mt-3 space-y-2">@foreach($integrations as $k => $v)<div><dt class="text-ink-500">{{ $k }}</dt><dd class="font-medium">{{ $v }}</dd></div>@endforeach</dl>
            <p class="mt-3 text-xs text-ink-500">Integration credentials are set in the server's <code>.env</code> file and are never shown here. See DEPLOYMENT.md.</p>
        </div>
        <div class="card card-body text-sm text-ink-600">
            <h2 class="text-base text-ink-900">Before launch</h2>
            <p class="mt-2">Business rules such as prices, zones, policies, COD procedures, proof requirements and retention periods must be decided by the owner. See the "Owner decisions" checklist in the README.</p>
        </div>
    </aside>
</div>
@endsection
