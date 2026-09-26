@props(['size' => 'size-9'])
<span {{ $attributes->merge(['class' => 'logo-mark grid place-items-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-md ring-1 ring-brand-900/10 '.$size]) }}>
    <svg viewBox="0 0 24 24" class="size-[58%]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/><path stroke="#fbcb5a" d="m7.5 4.27 9 5.15"/></svg>
</span>
