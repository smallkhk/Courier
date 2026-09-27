@extends('layouts.portal', ['portal' => 'business'])
@section('title', 'Bulk upload')
@section('content')
<x-page-header title="Bulk shipment upload" subtitle="Create up to {{ \App\Services\BulkImportService::MAX_ROWS }} shipments from a CSV file. Nothing is created until you confirm the preview." />
<div class="grid gap-6 lg:grid-cols-[1fr_380px]">
    <form method="post" action="{{ route('business.bulk.preview') }}" enctype="multipart/form-data" class="card card-body space-y-5">
        @csrf
        <ol class="space-y-2 text-sm text-ink-700">
            <li><strong>1.</strong> <a href="{{ route('business.bulk.template') }}">Download the CSV template</a> and fill one row per shipment.</li>
            <li><strong>2.</strong> Choose the pickup address and upload the file.</li>
            <li><strong>3.</strong> Review prices and any errors, then confirm.</li>
        </ol>
        @if($addresses->isEmpty())
            <x-alert type="warning">Add a pickup address first. <a href="{{ route('business.addresses.index') }}">Manage pickup addresses</a></x-alert>
        @else
            <x-field name="pickup_address_id" label="Pickup address" type="select" required :options="$addresses->mapWithKeys(fn($a) => [$a->id => $a->label.' — '.$a->oneLine()])" />
            <div>
                <label for="file" class="label">CSV file <span class="text-danger-700" aria-hidden="true">*</span></label>
                <input id="file" type="file" name="file" accept=".csv,text/csv" required class="input py-2 file:mr-3 file:rounded file:border-0 file:bg-brand-50 file:px-3 file:py-1 file:text-brand-700">
                @error('file')<p class="error-text"><x-icon name="alert" class="size-4" />{{ $message }}</p>@enderror
            </div>
            <button class="btn btn-primary" type="submit"><x-icon name="upload" class="size-4" />Upload & preview</button>
        @endif
    </form>
    <aside class="card card-body text-sm">
        <h2 class="text-base">Reference codes</h2>
        <h3 class="mt-3 font-semibold">delivery_country</h3>
        <p class="mt-1 text-xs text-ink-600">2-letter ISO code, e.g. <span class="font-mono">US</span>, <span class="font-mono">CA</span>, <span class="font-mono">GB</span>. Coverage is worked out from the country, state and ZIP/postal code.</p>
        <h3 class="mt-3 font-semibold">units</h3>
        <p class="mt-1 text-xs text-ink-600"><span class="font-mono">lb</span> (weight in lb, sizes in inches) or <span class="font-mono">kg</span> (kg / cm). Blank = {{ \App\Support\Units::system() === 'imperial' ? 'lb' : 'kg' }}.</p>
        <h3 class="mt-3 font-semibold">customs_*</h3>
        <p class="mt-1 text-xs text-ink-600">Required when the destination country differs from the pickup country. customs_contents_type: <span class="font-mono">{{ implode(', ', array_keys(\App\Models\Shipment::CUSTOMS_CONTENTS)) }}</span>.</p>
        <h3 class="mt-3 font-semibold">service_code</h3>
        <ul class="mt-1 space-y-0.5 font-mono text-xs">@foreach($services as $s)<li>{{ $s->code }} <span class="font-sans text-ink-500">— {{ $s->name }}</span></li>@endforeach</ul>
        <h3 class="mt-3 font-semibold">package_category</h3>
        <p class="mt-1 font-mono text-xs">{{ implode(', ', array_keys(\App\Models\Shipment::CATEGORIES)) }}</p>
    </aside>
</div>
@if($recent->isNotEmpty())
<div class="card mt-6"><div class="card-header"><h2 class="text-base">Recent uploads</h2></div>
    <ul class="divide-y divide-ink-100 text-sm">@foreach($recent as $r)<li class="flex justify-between p-4"><a href="{{ route('business.bulk.show', $r->id) }}">{{ $r->filename }}</a><span>{{ $r->valid_count }} valid · {{ $r->error_count }} errors · {{ ucfirst($r->status) }}</span></li>@endforeach</ul></div>
@endif
@endsection
