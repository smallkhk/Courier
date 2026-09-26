@props(['column', 'label'])
@php
    $current = request('sort'); $dir = request('dir', 'desc');
    $next = $current === $column && $dir === 'asc' ? 'desc' : 'asc';
@endphp
<a href="{{ request()->fullUrlWithQuery(['sort' => $column, 'dir' => $next, 'page' => null]) }}" class="inline-flex items-center gap-1 text-ink-600 no-underline hover:text-ink-900"
   @if($current === $column) aria-sort="{{ $dir === 'asc' ? 'ascending' : 'descending' }}" @endif>
    {{ $label }} @if($current === $column)<span aria-hidden="true">{{ $dir === 'asc' ? '▲' : '▼' }}</span>@endif
</a>
