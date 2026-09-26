@props(['icon' => 'package', 'title', 'action' => null, 'actionUrl' => null])
<div class="flex flex-col items-center justify-center px-6 py-12 text-center">
    <div class="mb-4 grid size-14 place-items-center rounded-full bg-brand-50 text-brand-600"><x-icon :name="$icon" class="size-7" /></div>
    <h3 class="text-base font-semibold">{{ $title }}</h3>
    @if(trim($slot))<p class="mt-1 max-w-sm text-sm text-ink-600">{{ $slot }}</p>@endif
    @if($action && $actionUrl)<a href="{{ $actionUrl }}" class="btn btn-primary mt-5"><x-icon name="plus" class="size-4" />{{ $action }}</a>@endif
</div>
