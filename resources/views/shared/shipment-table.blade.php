{{-- $shipments (paginator/collection), $showRoute (route name), $showOwner (bool) --}}
@php $tz = \App\Support\Settings::get('timezone'); @endphp
<div class="table-wrap">
<table class="table">
    <thead><tr>
        <th scope="col">Tracking</th>
        <th scope="col">Route</th>
        @if(!empty($showOwner))<th scope="col">Customer</th>@endif
        <th scope="col">Status</th>
        <th scope="col" class="text-right">Total</th>
        <th scope="col">Created</th>
    </tr></thead>
    <tbody>
    @foreach($shipments as $s)
        <tr>
            <td><a href="{{ route($showRoute, $s) }}" class="font-mono font-semibold">{{ $s->tracking_number }}</a><div class="text-xs text-ink-500">{{ $s->recipient_name }}</div></td>
            <td class="whitespace-nowrap">{{ $s->pickup_city }} <span aria-hidden="true">→</span><span class="sr-only">to</span> {{ $s->delivery_city }}<div class="text-xs text-ink-500">{{ $s->service?->name }}</div></td>
            @if(!empty($showOwner))<td>{{ $s->business?->name ?? $s->user?->name ?? 'Guest: '.$s->sender_name }}</td>@endif
            <td><x-status-badge :status="$s->status" /></td>
            <td class="text-right whitespace-nowrap"><x-money :amount="$s->total" :currency="$s->currency" /><div class="text-xs text-ink-500 uppercase">{{ $s->payment_method }}</div></td>
            <td class="whitespace-nowrap text-ink-600"><time datetime="{{ $s->created_at->toIso8601String() }}">{{ $s->created_at->timezone($tz)->format('j M Y') }}</time></td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
@if(method_exists($shipments, 'links'))<div class="border-t border-ink-200 p-4">{{ $shipments->links() }}</div>@endif
