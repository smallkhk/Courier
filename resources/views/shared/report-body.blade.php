<form method="get" class="mb-6 flex flex-wrap items-end gap-3">
    <x-field name="from" label="From" type="date" :value="$from->toDateString()" />
    <x-field name="to" label="To" type="date" :value="$to->toDateString()" />
    <button class="btn btn-secondary" type="submit">Update</button>
</form>
<div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
    <x-stat label="Booked" :value="$summary['booked']" icon="calendar" />
    <x-stat label="Delivered" :value="$summary['delivered']" icon="check-circle" />
    <x-stat label="Failed attempts" :value="$summary['failed_attempts']" icon="alert" />
    <x-stat label="On-time rate" :value="$summary['on_time_rate'] === null ? '—' : $summary['on_time_rate'].'%'" icon="clock" />
    <x-stat label="Exception rate" :value="$summary['exception_rate'] === null ? '—' : $summary['exception_rate'].'%'" icon="undo" />
</div>
<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <div class="card card-body">
        <h2 class="text-base">Verified payments received</h2>
        @forelse($summary['revenue'] as $cur => $amt)<p class="mt-2 text-2xl font-bold"><x-money :amount="$amt" :currency="$cur" /></p>@empty<p class="mt-2 text-ink-500">No verified payments in this period.</p>@endforelse
        <p class="mt-2 text-xs text-ink-500">Only provider-verified payments, net of processed refunds. Invoice and cash-on-delivery amounts are counted when paid.</p>
    </div>
    <div class="card card-body">
        <h2 class="text-base">Shipments created by current status</h2>
        @php $max = max(1, max($summary['by_status'] ?: [0])); @endphp
        <ul class="mt-3 space-y-2 text-sm">
            @forelse($summary['by_status'] as $st => $n)
                <li><div class="flex justify-between"><span>{{ \App\Enums\ShipmentStatus::from($st)->label() }}</span><span class="tabular-nums font-medium">{{ $n }}</span></div>
                    <div class="mt-1 h-2 rounded-full bg-ink-100"><div class="h-2 rounded-full bg-brand-500" style="width: {{ round($n / $max * 100) }}%"></div></div></li>
            @empty<li class="text-ink-500">No shipments in this period.</li>@endforelse
        </ul>
    </div>
</div>
<details class="card card-body mt-6 text-sm text-ink-600">
    <summary class="cursor-pointer font-medium text-ink-800">How these numbers are calculated</summary>
    <ul class="mt-3 list-disc space-y-1 pl-5">
        <li><strong>Booked</strong>: shipments confirmed (paid or on account) during the period.</li>
        <li><strong>Delivered</strong>: shipments marked delivered with proof during the period.</li>
        <li><strong>Failed attempts</strong>: delivery attempts recorded as unsuccessful during the period.</li>
        <li><strong>On-time rate</strong>: delivered shipments that had an estimate and arrived on or before the latest estimated date.</li>
        <li><strong>Exception rate</strong>: failed attempts ÷ (deliveries + failed attempts).</li>
    </ul>
</details>
