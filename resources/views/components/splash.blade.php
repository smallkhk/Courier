@php $brand = \App\Support\Settings::get('business_name'); @endphp
{{--
  Branded loading screen. Shown only on the first page view of a browser session,
  never without JavaScript and never for prefers-reduced-motion. It lifts away when
  the page has loaded (min 1.2s so the animation reads, hard cap 3.2s).
--}}
<script>
(function () {
    var h = document.documentElement, seen = false;
    try { seen = sessionStorage.getItem('splash-seen') === '1'; sessionStorage.setItem('splash-seen', '1'); } catch (e) { seen = true; }
    if (seen || (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches)) return;
    h.classList.add('js-splash');
    var start = Date.now(), done = false;
    function finish() {
        if (done) return; done = true;
        h.classList.add('splash-done');
        setTimeout(function () { var el = document.getElementById('splash'); if (el) el.remove(); h.classList.remove('js-splash', 'splash-done'); }, 800);
    }
    window.addEventListener('load', function () { setTimeout(finish, Math.max(0, 1200 - (Date.now() - start))); });
    setTimeout(finish, 3200);
})();
</script>
<div id="splash" role="status" aria-live="polite" aria-label="Loading {{ $brand }}">
    <div class="flex flex-col items-center gap-5 px-6 text-center">
        <div class="splash-logo-tile grid size-24 place-items-center rounded-3xl bg-gradient-to-br from-brand-400 to-brand-700 shadow-2xl ring-1 ring-white/20">
            <svg class="splash-logo size-14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path pathLength="100" d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
                <path pathLength="100" d="m3.3 7 8.7 5 8.7-5"/>
                <path pathLength="100" d="M12 22V12"/>
                <path pathLength="100" stroke="#fbcb5a" d="m7.5 4.27 9 5.15"/>
            </svg>
        </div>
        <p class="splash-name text-3xl font-extrabold tracking-tight" aria-hidden="true">
            @foreach(mb_str_split($brand) as $i => $ch)<span style="--i: {{ $i }}">{!! $ch === ' ' ? '&nbsp;' : e($ch) !!}</span>@endforeach
        </p>
        <div class="splash-road" aria-hidden="true">
            <svg class="splash-van size-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.62l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/></svg>
        </div>
        <div class="splash-bar" aria-hidden="true"><span></span></div>
        <p class="text-sm tracking-wide text-brand-200">Fast, safe, tracked delivery</p>
    </div>
</div>
<div id="nav-progress" aria-hidden="true"></div>
