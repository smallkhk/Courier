@props(['name', 'size' => 'size-14'])
@php
// Duotone illustrated icons: gradient tile + light and dark layers. Decorative (aria-hidden).
$uid = 'g'.substr(md5($name.microtime()), 0, 6);
$art = [
    'route' => '<circle cx="20" cy="42" r="6" fill="#fff"/><circle cx="20" cy="42" r="2.5" fill="var(--c2)"/><path d="M20 36c0-9 24-3 24-14" fill="none" stroke="#fff" stroke-width="3" stroke-dasharray="4 4" stroke-linecap="round"/><path d="M44 12a7 7 0 0 1 7 7c0 5.5-7 12-7 12s-7-6.5-7-12a7 7 0 0 1 7-7z" fill="#fff"/><circle cx="44" cy="19" r="2.8" fill="var(--c2)"/>',
    'wallet' => '<rect x="12" y="18" width="40" height="28" rx="6" fill="#fff"/><rect x="12" y="24" width="40" height="6" fill="var(--c2)" opacity=".35"/><rect x="36" y="32" width="16" height="10" rx="3" fill="var(--c2)"/><circle cx="42" cy="37" r="2" fill="#fff"/><path d="M22 14l14-4 3 8" fill="none" stroke="#fff" stroke-width="3" stroke-linejoin="round"/>',
    'track' => '<rect x="10" y="22" width="26" height="18" rx="3" fill="#fff"/><path d="M36 27h9l6 7v6H36z" fill="#fff" opacity=".85"/><circle cx="19" cy="42" r="4.5" fill="var(--c2)" stroke="#fff" stroke-width="2.5"/><circle cx="43" cy="42" r="4.5" fill="var(--c2)" stroke="#fff" stroke-width="2.5"/><path d="M15 30h14M15 34h9" stroke="var(--c2)" stroke-width="2.5" stroke-linecap="round"/>',
    'box' => '<path d="M32 10l20 10v24L32 54 12 44V20z" fill="#fff"/><path d="M12 20l20 10 20-10M32 30v24" fill="none" stroke="var(--c2)" stroke-width="2.5" stroke-linejoin="round"/><path d="M22 15l20 10v6" fill="none" stroke="var(--c2)" stroke-width="2.5" opacity=".5"/>',
    'check' => '<path d="M32 10l20 10v24L32 54 12 44V20z" fill="#fff"/><circle cx="44" cy="44" r="11" fill="var(--c2)" stroke="#fff" stroke-width="3"/><path d="M39 44l3.5 3.5L49 41" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>',
    'team' => '<circle cx="24" cy="24" r="7" fill="#fff"/><path d="M11 46c1-8 7-12 13-12s12 4 13 12z" fill="#fff"/><circle cx="42" cy="26" r="6" fill="#fff" opacity=".7"/><path d="M34 46c0-6 3-10 8-10s9 4 10 10z" fill="#fff" opacity=".7"/>',
    'invoice' => '<path d="M16 10h24l8 8v36l-4-3-4 3-4-3-4 3-4-3-4 3-4-3-4 3z" fill="#fff"/><path d="M22 24h18M22 31h18M22 38h10" stroke="var(--c2)" stroke-width="2.5" stroke-linecap="round"/>',
    'chart' => '<rect x="12" y="12" width="40" height="40" rx="6" fill="#fff"/><rect x="19" y="32" width="6" height="13" rx="2" fill="var(--c2)"/><rect x="29" y="24" width="6" height="21" rx="2" fill="var(--c2)" opacity=".7"/><rect x="39" y="18" width="6" height="27" rx="2" fill="var(--c2)"/>',
    'upload' => '<rect x="12" y="14" width="40" height="36" rx="5" fill="#fff"/><path d="M12 22h40" stroke="var(--c2)" stroke-width="2.5"/><path d="M32 44V30m-6 6l6-6 6 6" fill="none" stroke="var(--c2)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>',
    'shield' => '<path d="M32 9l19 7v13c0 13-9 21-19 26-10-5-19-13-19-26V16z" fill="#fff"/><path d="M24 32l6 6 11-12" fill="none" stroke="var(--c2)" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>',
    'clock' => '<circle cx="32" cy="32" r="21" fill="#fff"/><path d="M32 20v12l8 5" fill="none" stroke="var(--c2)" stroke-width="3.5" stroke-linecap="round"/><circle cx="32" cy="32" r="2.5" fill="var(--c2)"/>',
    'bike' => '<circle cx="18" cy="42" r="8" fill="none" stroke="#fff" stroke-width="3.5"/><circle cx="46" cy="42" r="8" fill="none" stroke="#fff" stroke-width="3.5"/><path d="M18 42l9-14h12l7 14M30 28l-4-8h-6" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/><rect x="34" y="14" width="14" height="11" rx="2" fill="var(--c2)" stroke="#fff" stroke-width="2.5"/>',
    'support' => '<path d="M14 18a6 6 0 0 1 6-6h24a6 6 0 0 1 6 6v16a6 6 0 0 1-6 6H28l-10 8v-8h2a6 6 0 0 1-6-6z" fill="#fff"/><circle cx="24" cy="26" r="2.5" fill="var(--c2)"/><circle cx="32" cy="26" r="2.5" fill="var(--c2)"/><circle cx="40" cy="26" r="2.5" fill="var(--c2)"/>',
    'globe' => '<circle cx="32" cy="32" r="21" fill="#fff"/><path d="M11 32h42M32 11c7 7 7 35 0 42M32 11c-7 7-7 35 0 42" fill="none" stroke="var(--c2)" stroke-width="2.5"/>',
    'pin' => '<path d="M32 8a15 15 0 0 1 15 15c0 12-15 30-15 30S17 35 17 23A15 15 0 0 1 32 8z" fill="#fff"/><circle cx="32" cy="23" r="6" fill="var(--c2)"/><ellipse cx="32" cy="55" rx="10" ry="2.5" fill="#fff" opacity=".5"/>',
];
$tones = ['brand' => ['#3461ee', '#0f2f8f'], 'accent' => ['#f5b41e', '#b87a06'], 'success' => ['#10b981', '#047857'], 'violet' => ['#8b5cf6', '#5b21b6'], 'sky' => ['#38bdf8', '#0369a1'], 'rose' => ['#fb7185', '#be123c']];
[$c1, $c2] = $tones[$attributes->get('tone', 'brand')] ?? $tones['brand'];
@endphp
<svg viewBox="0 0 64 64" aria-hidden="true" focusable="false" {{ $attributes->except('tone')->merge(['class' => 'shrink-0 drop-shadow-md '.$size]) }} style="--c2: {{ $c2 }}">
    <defs><linearGradient id="{{ $uid }}" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{{ $c1 }}"/><stop offset="1" stop-color="{{ $c2 }}"/></linearGradient></defs>
    <rect width="64" height="64" rx="16" fill="url(#{{ $uid }})"/>
    <rect x="1" y="1" width="62" height="31" rx="15" fill="#fff" opacity=".12"/>
    {!! $art[$name] ?? $art['box'] !!}
</svg>
