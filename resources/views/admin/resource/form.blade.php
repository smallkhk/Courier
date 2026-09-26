@extends('layouts.portal', ['portal' => 'ops'])
@section('title', ($model->exists ? 'Edit ' : 'Add ').$def['singular'])
@section('content')
<div class="max-w-3xl">
<x-page-header :title="($model->exists ? 'Edit ' : 'Add ').$def['singular']" :back="route('admin.resource.index', $resource)" />
@isset($def['help'])<x-alert type="info" class="mb-4">{{ $def['help'] }}</x-alert>@endisset
<form method="post" action="{{ $model->exists ? route('admin.resource.update', [$resource, $model->id]) : route('admin.resource.store', $resource) }}" class="card card-body grid gap-4 sm:grid-cols-2">
    @csrf @if($model->exists) @method('PUT') @endif
    @foreach($def['fields'] as $name => $f)
        @php [$type, $label] = $f; $opts = isset($f[3]) ? ($f[3])() : null; $val = $model->{$name}; $req = str_contains($f[2], 'required') && !str_contains($f[2], 'required_if'); @endphp
        @switch($type)
            @case('bool')
                <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" class="checkbox" name="{{ $name }}" value="1" @checked(old($name, $val))> {{ $label }}</label>
                @break
            @case('textarea')
                <x-field :name="$name" :label="$label" type="textarea" rows="{{ $name === 'body' ? 12 : 4 }}" :value="$val" :required="$req" class="sm:col-span-2" />
                @break
            @case('select')
                <x-field :name="$name" :label="$label" type="select" :options="$opts" :value="$val" :required="$req" :placeholder="$req ? 'Select…' : '— Any / none —'" />
                @break
            @case('relation')
                @php $selected = old($name, $model->exists ? $model->{$name}->pluck('id')->all() : []); @endphp
                <fieldset class="sm:col-span-2"><legend class="label">{{ $label }}</legend>
                    <div class="grid gap-2 sm:grid-cols-3">@foreach($opts as $id => $l)<label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" name="{{ $name }}[]" value="{{ $id }}" @checked(in_array($id, $selected))> {{ $l }}</label>@endforeach</div>
                </fieldset>
                @break
            @case('list')
                <x-field :name="$name" :label="$label" type="textarea" rows="2" :value="is_array($val) ? implode(', ', $val) : $val" class="sm:col-span-2" />
                @break
            @case('hours')
                <fieldset class="sm:col-span-2"><legend class="label">{{ $label }}</legend>
                    <div class="grid gap-2 sm:grid-cols-4">@foreach(\App\Models\Branch::DAYS as $k => $day)<div><label class="text-xs text-ink-600" for="h{{ $k }}">{{ $day }}</label><input id="h{{ $k }}" class="input" name="{{ $name }}[{{ $k }}]" value="{{ old($name.'.'.$k, $val[$k] ?? '') }}" placeholder="Closed"></div>@endforeach</div>
                </fieldset>
                @break
            @case('closures')
                <x-field :name="$name" :label="$label" type="textarea" rows="3" :value="collect($val ?? [])->map(fn($c) => $c['date'].' '.($c['note'] ?? ''))->implode(PHP_EOL)" class="sm:col-span-2" />
                @break
            @case('date')
                <x-field :name="$name" :label="$label" type="date" :value="$val?->toDateString()" :required="$req" />
                @break
            @case('money')
            @case('percent')
            @case('number')
                <x-field :name="$name" :label="$label" type="number" step="any" :value="$val" :required="$req" />
                @break
            @default
                <x-field :name="$name" :label="$label" :value="$val" :required="$req" />
        @endswitch
    @endforeach
    <div class="flex gap-2 sm:col-span-2"><button class="btn btn-primary" type="submit">Save</button><a href="{{ route('admin.resource.index', $resource) }}" class="btn btn-ghost">Cancel</a></div>
</form>
@if($model->exists)
<form method="post" action="{{ route('admin.resource.destroy', [$resource, $model->id]) }}" class="mt-4" x-data @submit="if(!confirm('Delete permanently? Prefer marking inactive if it has been used.')) $event.preventDefault()">
    @csrf @method('DELETE')<button class="btn btn-ghost text-danger-700" type="submit">Delete {{ $def['singular'] }}</button>
</form>
@endif
</div>
@endsection
