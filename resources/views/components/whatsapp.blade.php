@php $wa = preg_replace('/\D/', '', (string) \App\Support\Settings::get('whatsapp_number')); @endphp
@if($wa)
{{-- Floating WhatsApp chat button (shown only when a number is set in Admin → System settings). --}}
<a href="https://wa.me/{{ $wa }}?text={{ rawurlencode('Hello '.\App\Support\Settings::get('business_name').', I need help with a delivery.') }}"
   target="_blank" rel="noopener" class="group fixed right-4 bottom-4 z-40 flex items-center gap-2 no-underline hover:no-underline sm:right-6 sm:bottom-6" aria-label="Chat with us on WhatsApp (opens in a new tab)">
    <span class="hidden rounded-full bg-white px-3 py-1.5 text-sm font-semibold text-ink-800 opacity-0 shadow-lg transition group-hover:opacity-100 sm:block">Chat with us</span>
    <span class="relative grid size-14 place-items-center rounded-full bg-[#25D366] text-white shadow-xl transition group-hover:scale-110">
        <span class="wa-ring absolute inset-0 rounded-full bg-[#25D366]"></span>
        <svg class="relative size-7" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.1l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6a2.7 2.7 0 0 0 1.8-1.2 2.2 2.2 0 0 0 .1-1.3c0-.1-.2-.2-.4-.3Z"/></svg>
    </span>
</a>
@endif
