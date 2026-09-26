@props(['icon' => 'package', 'title', 'action' => null, 'actionUrl' => null])
<div class="flex flex-col items-center justify-center px-6 py-12 text-center">
    <div class="relative mb-5 grid size-16 animate-float place-items-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-800 text-white shadow-lg"><x-icon :name="$icon" class="size-8" /><span class="absolute -right-1 -bottom-1 size-5 rounded-full bg-accent-400 ring-4 ring-white"></span></div>
    <h3 class="text-base font-semibold">{{ $title }}</h3>
    @if(trim($slot))<p class="mt-1 max-w-sm text-sm text-ink-600">{{ $slot }}</p>@endif
    @if($action && $actionUrl)<a href="{{ $actionUrl }}" class="btn btn-primary mt-5"><x-icon name="plus" class="size-4" />{{ $action }}</a>@endif
</div>
