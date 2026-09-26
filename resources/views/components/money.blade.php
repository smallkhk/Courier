@props(['amount', 'currency'])
<span class="tabular-nums">{{ \App\Support\Money::format($amount, $currency) }}</span>
