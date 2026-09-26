@props(['status'])
@php $s = $status instanceof \App\Enums\ShipmentStatus ? $status : \App\Enums\ShipmentStatus::from($status); @endphp
{{-- Status is conveyed by icon + text, never color alone. --}}
<span {{ $attributes->merge(['class' => 'badge badge-'.$s->tone()]) }}>
    <x-icon :name="$s->icon()" class="size-3.5" />
    {{ $s->label() }}
</span>
