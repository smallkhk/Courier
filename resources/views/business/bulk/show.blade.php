@extends('layouts.portal', ['portal' => 'business'])
@section('title', 'Import preview')
@section('content')
@php $items = $import->rows['items']; $warnings = collect($items)->filter(fn($r) => !$r['errors'] && $r['warnings'])->count(); @endphp
<x-page-header :title="'Preview: '.$import->filename" :back="route('business.bulk.create')" />
<div class="mb-6 grid grid-cols-3 gap-4">
    <x-stat label="Ready" :value="$import->valid_count - $warnings" icon="check-circle" />
    <x-stat label="Possible duplicates" :value="$warnings" icon="alert" />
    <x-stat label="Errors (skipped)" :value="$import->error_count" icon="x" />
</div>
@if($import->status === 'previewed')
<form method="post" action="{{ route('business.bulk.confirm', $import->id) }}" class="card card-body mb-6 flex flex-wrap items-center justify-between gap-4">
    @csrf
    <div class="text-sm">
        <p>Rows with errors are skipped. Fix them in your file and upload again.</p>
        @if($warnings)<label class="mt-2 flex items-center gap-2"><input type="checkbox" class="checkbox" name="include_warnings" value="1"> Also create {{ $warnings }} possible duplicate(s)</label>@endif
        <p class="mt-1 text-ink-500">Prices are recalculated at confirmation. {{ $business->billsByInvoice() ? 'Shipments are billed on your invoice.' : 'You can pay for them together after creation.' }}</p>
    </div>
    <button class="btn btn-primary" type="submit" @disabled($import->valid_count === 0)>Create shipments</button>
</form>
@else
<x-alert type="info" class="mb-6">This import was {{ $import->status }} {{ $import->confirmed_at?->diffForHumans() }}.</x-alert>
@endif
<div class="card"><div class="table-wrap"><table class="table">
    <thead><tr><th>Row</th><th>Recipient</th><th>Destination</th><th>Price</th><th>Result</th></tr></thead>
    <tbody>
    @foreach($items as $r)
        <tr class="{{ $r['errors'] ? 'bg-danger-50/50' : ($r['warnings'] ? 'bg-warning-50/50' : '') }}">
            <td>{{ $r['line'] }}</td>
            <td>{{ $r['data']['recipient_name'] ?? '' }}<div class="text-xs text-ink-500">{{ $r['data']['recipient_phone'] ?? '' }}</div></td>
            <td>{{ \App\Support\Countries::flag($r['data']['delivery_country'] ?? '') }} {{ collect([$r['data']['delivery_city'] ?? null, $r['data']['delivery_region'] ?? null, $r['data']['delivery_postal_code'] ?? null])->filter()->implode(', ') }}<div class="text-xs text-ink-500">{{ $r['data']['service_code'] ?? '' }}</div></td>
            <td>@if($r['quote'])<x-money :amount="$r['quote']['total']" :currency="$r['quote']['currency']" />@else—@endif</td>
            <td>
                @if($r['errors'])<x-pill tone="danger" icon="x">Error</x-pill><ul class="mt-1 list-disc pl-4 text-xs text-danger-700">@foreach($r['errors'] as $e)<li>{{ $e }}</li>@endforeach</ul>
                @elseif($r['warnings'])<x-pill tone="warning" icon="alert">Check</x-pill><ul class="mt-1 list-disc pl-4 text-xs text-warning-800">@foreach($r['warnings'] as $e)<li>{{ $e }}</li>@endforeach</ul>
                @else<x-pill tone="success" icon="check">Ready</x-pill>@endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table></div></div>
@endsection
