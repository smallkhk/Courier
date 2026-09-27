@props(['breakdown', 'currency'])
<dl class="divide-y divide-ink-100 text-sm">
    @foreach($breakdown['lines'] ?? [] as $line)
        <div class="flex justify-between gap-4 py-2"><dt class="text-ink-600">{{ $line['label'] }}</dt><dd class="tabular-nums">{{ \App\Support\Money::format($line['amount'], $currency) }}</dd></div>
    @endforeach
    <div class="flex justify-between gap-4 pt-3 text-base font-bold text-ink-900"><dt>Total</dt><dd class="tabular-nums">{{ \App\Support\Money::format($breakdown['total'], $currency) }}</dd></div>
</dl>
<p class="mt-2 text-xs text-ink-500">Chargeable weight: {{ isset($breakdown['weight_unit']) ? rtrim(rtrim(number_format($breakdown['chargeable_weight'], 2), '0'), '.').' '.$breakdown['weight_unit'] : \App\Support\Units::weight($breakdown['chargeable_weight_kg']) }} (greater of actual and dimensional weight).</p>
