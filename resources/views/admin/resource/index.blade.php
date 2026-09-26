@extends('layouts.portal', ['portal' => 'ops'])
@section('title', $def['title'])
@section('content')
<x-page-header :title="$def['title']"><a href="{{ route('admin.resource.create', $resource) }}" class="btn btn-primary"><x-icon name="plus" class="size-4" />Add {{ $def['singular'] }}</a></x-page-header>
@isset($def['help'])<x-alert type="info" class="mb-4">{{ $def['help'] }}</x-alert>@endisset
<form method="get" class="mb-4 flex items-end gap-3" role="search"><x-field name="q" label="Search" :value="request('q')" /><button class="btn btn-secondary" type="submit">Search</button></form>
<div class="card">@if($rows->isEmpty())<x-empty icon="layers" :title="'No '.$def['title'].' yet'" :action="'Add '.$def['singular']" :action-url="route('admin.resource.create', $resource)" />@else
<div class="table-wrap"><table class="table">
    <thead><tr>@foreach($def['columns'] as $label)<th>{{ $label }}</th>@endforeach<th><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>@foreach($rows as $row)<tr>
        @foreach($def['columns'] as $col => $label)
            @php $v = data_get($row, $col); @endphp
            <td>@if(is_bool($v))<x-pill :tone="$v ? 'success' : 'neutral'" :icon="$v ? 'check' : 'x'">{{ $v ? 'Yes' : 'No' }}</x-pill>@elseif($v instanceof \Carbon\CarbonInterface){{ $v->format('j M Y') }}@else{{ \Illuminate\Support\Str::limit((string) ($v ?? '—'), 60) }}@endif</td>
        @endforeach
        <td class="text-right whitespace-nowrap"><a href="{{ route('admin.resource.edit', [$resource, $row->id]) }}" class="btn btn-ghost btn-sm">Edit</a></td>
    </tr>@endforeach</tbody>
</table></div><div class="border-t border-ink-200 p-4">{{ $rows->links() }}</div>@endif</div>
@endsection
