<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\ContentBlock;
use App\Models\Faq;
use App\Models\Service;
use App\Models\ServiceZone;
use App\Services\PricingService;
use App\Services\ZoneResolver;
use App\Support\Countries;
use App\Support\Settings;
use App\Support\Units;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PublicController extends Controller
{
    public function home()
    {
        return view('public.home', [
            'services' => Service::where('active', true)->orderBy('sort_order')->get(),
            'zones' => ServiceZone::where('active', true)->get(),
            'branchCount' => Branch::where('active', true)->count(),
        ]);
    }

    public function services()
    {
        return view('public.services', ['services' => Service::where('active', true)->with('zones')->orderBy('sort_order')->get()]);
    }

    public function pricing()
    {
        return view('public.pricing', $this->quoteFormData());
    }

    /** Public estimate from origin/destination locations (booking re-quotes with full details). */
    public function estimate(Request $request, PricingService $pricing, ZoneResolver $zones)
    {
        $data = $request->validate([
            'from_country' => 'required|string|size:2', 'from_region' => 'nullable|string|max:100', 'from_city' => 'nullable|string|max:100', 'from_postal_code' => 'nullable|string|max:20',
            'to_country' => 'required|string|size:2', 'to_region' => 'nullable|string|max:100', 'to_city' => 'nullable|string|max:100', 'to_postal_code' => 'nullable|string|max:20',
            'service_id' => 'required|integer|exists:services,id',
            'units' => 'nullable|in:imperial,metric',
            'weight' => 'required|numeric|min:0.01|max:5000',
            'length' => 'nullable|numeric|min:0.1|max:1000',
            'width' => 'nullable|numeric|min:0.1|max:1000',
            'height' => 'nullable|numeric|min:0.1|max:1000',
            'parcel_count' => 'required|integer|min:1|max:20',
            'declared_value' => 'nullable|numeric|min:0|max:100000000',
            'insured' => 'nullable|boolean',
            'pickup_requested' => 'nullable|boolean',
        ]);
        $origin = $zones->resolve($data['from_country'], $data['from_region'] ?? null, $data['from_postal_code'] ?? null, $data['from_city'] ?? null);
        $dest = $zones->resolve($data['to_country'], $data['to_region'] ?? null, $data['to_postal_code'] ?? null, $data['to_city'] ?? null);
        $errors = [];
        if (! $origin) {
            $errors['from_country'] = 'We don\'t pick up from that location yet (add the state or ZIP code if you left it out).';
        }
        if (! $dest) {
            $errors['to_country'] = 'We don\'t deliver to that location yet (add the state or ZIP code if you left it out).';
        }
        if ($errors) {
            return back()->withErrors($errors)->withInput();
        }
        $system = $data['units'] ?? Units::system();
        $parcel = array_filter([
            'weight_kg' => Units::toKg($data['weight'], $system),
            'length_cm' => Units::toCm($data['length'] ?? null, $system),
            'width_cm' => Units::toCm($data['width'] ?? null, $system),
            'height_cm' => Units::toCm($data['height'] ?? null, $system),
        ], fn ($v) => $v !== null);
        try {
            $quote = $pricing->quote([
                'service_id' => (int) $data['service_id'],
                'origin_zone_id' => $origin->id,
                'destination_zone_id' => $dest->id,
                'parcels' => array_fill(0, (int) $data['parcel_count'], $parcel),
                'declared_value' => $data['declared_value'] ?? 0,
                'insured' => (bool) ($data['insured'] ?? false),
                'pickup_requested' => (bool) ($data['pickup_requested'] ?? false),
            ], $request->user()?->id);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return view('public.pricing', $this->quoteFormData() + ['quote' => $quote->load('service'), 'route' => [$origin, $dest]]);
    }

    private function quoteFormData(): array
    {
        return [
            'services' => Service::where('active', true)->orderBy('sort_order')->get(),
        ];
    }

    public function coverage()
    {
        return view('public.coverage', [
            'zones' => ServiceZone::where('active', true)->with(['services' => fn ($q) => $q->where('active', true)])->orderBy('name')->get()
                ->groupBy(fn ($z) => $z->country_code ? Countries::name($z->country_code) : 'International (rest of world)')
                ->sortKeysUsing(fn ($a, $b) => [$a === Countries::name(Settings::get('default_country')) ? 0 : 1, $a] <=> [$b === Countries::name(Settings::get('default_country')) ? 0 : 1, $b]),
        ]);
    }

    public function branches(Request $request)
    {
        $branches = Branch::where('active', true)
            ->when($request->filled('country'), fn ($q) => $q->where('country_code', $request->string('country')))
            ->orderBy('country_code')->orderBy('region')->orderBy('city')->get();

        return view('public.branches', [
            'branches' => $branches,
            'countries' => Branch::where('active', true)->distinct()->pluck('country_code')->mapWithKeys(fn ($c) => [$c => Countries::name($c)])->sort(),
            'mapPoints' => $branches->map(fn ($b) => ['name' => $b->name, 'lat' => $b->lat, 'lng' => $b->lng, 'address' => $b->fullAddress(), 'directions' => $b->directionsUrl()])->values(),
        ]);
    }

    public function about()
    {
        return view('public.content', ['block' => ContentBlock::where('key', 'about')->first(), 'fallbackTitle' => 'About us']);
    }

    public function faq()
    {
        return view('public.faq', ['faqs' => Faq::where('active', true)->orderBy('sort_order')->get()]);
    }

    public function legal(string $page)
    {
        $titles = ['terms' => 'Terms of service', 'privacy' => 'Privacy policy', 'delivery-policy' => 'Delivery & claims policy'];

        return view('public.content', ['block' => ContentBlock::where('key', $page)->first(), 'fallbackTitle' => $titles[$page]]);
    }

    public function business()
    {
        return view('public.business');
    }
}
