@props(['type' => 'info', 'title' => null])
@php $icon = ['info' => 'info', 'success' => 'check-circle', 'warning' => 'alert', 'danger' => 'alert'][$type] ?? 'info'; @endphp
<div {{ $attributes->merge(['class' => 'alert alert-'.$type]) }} role="{{ in_array($type, ['danger', 'warning']) ? 'alert' : 'status' }}">
    <x-icon :name="$icon" class="mt-0.5 size-5" />
    <div class="min-w-0">
        @if($title)<p class="font-semibold">{{ $title }}</p>@endif
        <div>{{ $slot }}</div>
    </div>
</div>
