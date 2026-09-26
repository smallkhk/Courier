@props(['title', 'subtitle' => null, 'image' => 'courier-boxes', 'icon' => 'user'])
{{-- Split-screen auth layout: form on the left, photo panel on the right (hidden on small screens). --}}
<div class="grid min-h-[calc(100vh-4rem)] lg:grid-cols-2">
    <div class="flex items-center justify-center px-4 py-12 sm:px-6">
        <div class="w-full max-w-md hero-rise">
            <div>
                <x-illus name="{{ $icon === 'mail' ? 'support' : 'shield' }}" size="size-14" />
                <h1 class="mt-5 text-3xl">{{ $title }}</h1>
                @if($subtitle)<p class="mt-2 text-ink-600">{{ $subtitle }}</p>@endif
            </div>
            <div class="mt-4"><x-flash /></div>
            <div class="mt-4">{{ $slot }}</div>
        </div>
    </div>
    <div class="photo-hero hidden lg:flex">
        <img src="{{ asset('images/'.$image.'.webp') }}" alt="">
        <div class="mt-auto p-12">
            <div class="glass max-w-md animate-float-slow rounded-2xl p-6">
                <div class="flex items-center gap-3">
                    <x-illus name="check" tone="success" size="size-11" />
                    <p class="font-semibold text-white">Every delivery, accounted for</p>
                </div>
                <ul class="mt-4 space-y-2 text-sm text-brand-100">
                    <li class="flex gap-2"><x-icon name="check" class="size-4 text-accent-300" />Book and pay in minutes</li>
                    <li class="flex gap-2"><x-icon name="check" class="size-4 text-accent-300" />Follow every step of the journey</li>
                    <li class="flex gap-2"><x-icon name="check" class="size-4 text-accent-300" />Signed proof when it arrives</li>
                </ul>
            </div>
        </div>
    </div>
</div>
