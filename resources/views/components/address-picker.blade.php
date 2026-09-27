@props(['prefix', 'legend', 'values' => [], 'saved' => [], 'compact' => false])
@php
    use App\Support\Countries;
    use App\Support\Regions;
    $f = fn ($k) => $prefix.'_'.$k;
    $v = fn ($k, $d = null) => old($f($k), $values[$k] ?? $d);
    $cfg = [
        'prefix' => $prefix,
        'provider' => config('courier.address_autocomplete.provider'),
        'googleKey' => config('courier.address_autocomplete.google_key'),
        'osmUrl' => config('courier.address_autocomplete.osm_url'),
        'tileUrl' => config('courier.maps.tile_url'),
        'attribution' => config('courier.maps.attribution'),
        'regions' => ['US' => Regions::US, 'CA' => Regions::CA],
        'saved' => $saved,
        'values' => [
            'address' => $v('address'), 'address2' => $v('address2'), 'city' => $v('city'), 'region' => $v('region'),
            'postal_code' => $v('postal_code'), 'country' => $v('country', \App\Support\Settings::get('default_country', 'US')),
            'lat' => $v('lat'), 'lng' => $v('lng'), 'place_id' => $v('place_id'),
        ],
    ];
    $err = fn ($k) => $errors->first($f($k));
    $countries = Countries::options();
@endphp
<fieldset x-data="addressField(@js($cfg))" class="space-y-4" {{ $attributes }}>
    <legend class="sr-only">{{ $legend }}</legend>

    @if($saved)
        <div>
            <label class="label" for="{{ $prefix }}_saved">Use a saved address</label>
            <select id="{{ $prefix }}_saved" class="input" @change="useSaved($event.target.value)">
                <option value="">Choose…</option>
                @foreach($saved as $s)<option value="{{ $s['id'] }}">{{ $s['label'] }} — {{ $s['city'] }}, {{ $s['country'] }}</option>@endforeach
            </select>
        </div>
    @endif

    {{-- Search (combobox) --}}
    <div class="relative" x-show="provider !== 'none'" @click.outside="open = false">
        <label class="label" :for="id('search')">Search for an address</label>
        <div class="relative">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-ink-400" />
            <input type="text" :id="id('search')" x-model="query" @input.debounce.250ms="suggest()" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)"
                   @keydown.enter="if (open && active >= 0) { $event.preventDefault(); pick(active) }" @keydown.escape="open = false" @focus="open = results.length > 0"
                   class="input pl-10" autocomplete="off" placeholder="{{ $compact ? 'Search a city or ZIP / postal code' : 'Start typing a street address, anywhere in the world' }}"
                   role="combobox" aria-autocomplete="list" :aria-expanded="open.toString()" :aria-controls="id('list')" :aria-activedescendant="active >= 0 ? id('opt' + active) : ''">
            <span x-show="loading" class="spinner absolute top-1/2 right-3 -translate-y-1/2 text-brand-600" aria-hidden="true"></span>
        </div>
        <ul x-cloak x-show="open" x-transition.opacity :id="id('list')" role="listbox" class="absolute z-30 mt-1 max-h-80 w-full overflow-auto rounded-[var(--radius-md)] border border-ink-200 bg-white py-1 shadow-xl">
            <template x-for="(r, n) in results" :key="n">
                <li :id="id('opt' + n)" role="option" :aria-selected="(active === n).toString()" @mousedown.prevent="pick(n)" @mouseenter="active = n"
                    class="flex cursor-pointer items-start gap-3 px-3 py-2.5" :class="active === n ? 'bg-brand-50' : ''">
                    <x-icon name="map-pin" class="mt-0.5 size-4 text-brand-600" />
                    <span class="min-w-0"><span class="block truncate font-medium text-ink-900" x-text="r.main"></span><span class="block truncate text-xs text-ink-500" x-text="r.secondary"></span></span>
                </li>
            </template>
            <li x-show="!loading && results.length === 0 && query.length > 2" class="px-3 py-2.5 text-sm text-ink-500">No matches — enter the address manually below.</li>
            <li class="border-t border-ink-100 px-3 pt-1.5 pb-1 text-right text-[11px] text-ink-400" x-text="provider === 'google' ? 'Powered by Google' : 'Search © OpenStreetMap contributors (Photon)'"></li>
        </ul>
        <p class="hint" x-show="!notice">Pick a suggestion to fill in the address, or type it manually below.</p>
        <p class="hint text-warning-800" x-show="notice" x-text="notice"></p>
    </div>

    {{-- Map preview with draggable pin --}}
    @unless($compact)
    <div x-show="hasPoint()" x-cloak class="overflow-hidden rounded-[var(--radius-md)] border border-ink-200">
        <div x-ref="map" class="h-48 w-full bg-ink-100" role="img" aria-label="Map showing the selected address"></div>
        <p class="border-t border-ink-100 bg-ink-50 px-3 py-1.5 text-xs text-ink-600">Drag the pin to the exact entrance if needed.</p>
    </div>
    @endunless

    {{-- Address fields (always editable) --}}
    <div class="grid gap-4 sm:grid-cols-6">
        <div class="sm:col-span-6">
            <label class="label" :for="id('country')">Country <span class="text-danger-700" aria-hidden="true">*</span></label>
            <select :id="id('country')" name="{{ $f('country') }}" x-model="a.country" @change="manualEdit(); a.region = ''" class="input" required aria-invalid="{{ $err('country') ? 'true' : 'false' }}">
                @foreach($countries as $code => $name)<option value="{{ $code }}">{{ Countries::flag($code) }} {{ $name }}</option>@endforeach
            </select>
            @if($err('country'))<p class="error-text"><x-icon name="alert" class="size-4" />{{ $err('country') }}</p>@endif
        </div>
        @unless($compact)
        <div class="sm:col-span-4">
            <label class="label" :for="id('address')">Street address <span class="text-danger-700" aria-hidden="true">*</span></label>
            <input :id="id('address')" name="{{ $f('address') }}" x-model="a.address" @input="manualEdit()" class="input" required autocomplete="address-line1" aria-invalid="{{ $err('address') ? 'true' : 'false' }}">
            @if($err('address'))<p class="error-text"><x-icon name="alert" class="size-4" />{{ $err('address') }}</p>@endif
        </div>
        <div class="sm:col-span-2">
            <label class="label" :for="id('address2')">Apt, suite, unit</label>
            <input :id="id('address2')" name="{{ $f('address2') }}" x-model="a.address2" class="input" autocomplete="address-line2">
        </div>
        @endunless
        <div class="sm:col-span-2">
            <label class="label" :for="id('city')">City / town @unless($compact)<span class="text-danger-700" aria-hidden="true">*</span>@endunless</label>
            <input :id="id('city')" name="{{ $f('city') }}" x-model="a.city" @input="manualEdit()" class="input" @unless($compact) required @endunless autocomplete="address-level2" aria-invalid="{{ $err('city') ? 'true' : 'false' }}">
            @if($err('city'))<p class="error-text"><x-icon name="alert" class="size-4" />{{ $err('city') }}</p>@endif
        </div>
        <div class="sm:col-span-2">
            <label class="label" :for="id('region')" x-text="a.country === 'US' ? 'State' : (a.country === 'CA' ? 'Province' : 'State / region')">State / region</label>
            <template x-if="regionList()">
                <select :id="id('region')" name="{{ $f('region') }}" x-model="a.region" class="input" @unless($compact) required @endunless>
                    <option value="">Select…</option>
                    <template x-for="[code, name] in Object.entries(regionList())" :key="code"><option :value="code" x-text="name" :selected="a.region === code"></option></template>
                </select>
            </template>
            <template x-if="!regionList()">
                <input :id="id('region')" name="{{ $f('region') }}" x-model="a.region" class="input" autocomplete="address-level1">
            </template>
            @if($err('region'))<p class="error-text"><x-icon name="alert" class="size-4" />{{ $err('region') }}</p>@endif
        </div>
        <div class="sm:col-span-2">
            <label class="label" :for="id('postal')" x-text="a.country === 'US' ? 'ZIP code' : 'Postal code'">Postal code</label>
            <input :id="id('postal')" name="{{ $f('postal_code') }}" x-model="a.postal_code" @input="manualEdit()" class="input" autocomplete="postal-code" aria-invalid="{{ $err('postal_code') ? 'true' : 'false' }}">
            @if($err('postal_code'))<p class="error-text"><x-icon name="alert" class="size-4" />{{ $err('postal_code') }}</p>@endif
        </div>
    </div>
    <input type="hidden" name="{{ $f('lat') }}" :value="a.lat ?? ''">
    <input type="hidden" name="{{ $f('lng') }}" :value="a.lng ?? ''">
    <input type="hidden" name="{{ $f('place_id') }}" :value="a.place_id ?? ''">
</fieldset>
