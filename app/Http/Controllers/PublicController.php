<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\ContentBlock;
use App\Models\Faq;
use App\Models\Service;
use App\Models\ServiceZone;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PublicController extends Controller
{
    public function home()
    {
        return view('public.home', [
            'services' => Service::where('active', true)->orderBy('sort_order')->get(),
            'zones' => ServiceZone::where('active', true)->orderBy('state')->get(),
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

    /** Public estimate (not bookable on its own; booking re-quotes with full details). */
    public function estimate(Request $request, PricingService $pricing)
    {
        $data = $request->validate([
            'origin_zone_id' => 'required|integer|exists:service_zones,id',
            'destination_zone_id' => 'required|integer|exists:service_zones,id',
            'service_id' => 'required|integer|exists:services,id',
            'weight_kg' => 'required|numeric|min:0.01|max:1000',
            'length_cm' => 'nullable|numeric|min:1|max:500',
            'width_cm' => 'nullable|numeric|min:1|max:500',
            'height_cm' => 'nullable|numeric|min:1|max:500',
            'parcel_count' => 'required|integer|min:1|max:20',
            'declared_value' => 'nullable|numeric|min:0|max:100000000',
            'insured' => 'nullable|boolean',
            'pickup_requested' => 'nullable|boolean',
        ]);
        $parcel = array_filter(['weight_kg' => $data['weight_kg'], 'length_cm' => $data['length_cm'] ?? null, 'width_cm' => $data['width_cm'] ?? null, 'height_cm' => $data['height_cm'] ?? null], fn ($v) => $v !== null);
        try {
            $quote = $pricing->quote([
                'service_id' => (int) $data['service_id'],
                'origin_zone_id' => (int) $data['origin_zone_id'],
                'destination_zone_id' => (int) $data['destination_zone_id'],
                'parcels' => array_fill(0, (int) $data['parcel_count'], $parcel),
                'declared_value' => $data['declared_value'] ?? 0,
                'insured' => (bool) ($data['insured'] ?? false),
                'pickup_requested' => (bool) ($data['pickup_requested'] ?? false),
            ], $request->user()?->id);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return view('public.pricing', $this->quoteFormData() + ['quote' => $quote->load('service')]);
    }

    private function quoteFormData(): array
    {
        return [
            'zones' => ServiceZone::where('active', true)->orderBy('state')->orderBy('name')->get(),
            'services' => Service::where('active', true)->orderBy('sort_order')->get(),
        ];
    }

    public function coverage()
    {
        return view('public.coverage', [
            'zones' => ServiceZone::where('active', true)->with(['services' => fn ($q) => $q->where('active', true)])->orderBy('state')->orderBy('name')->get()->groupBy('state'),
        ]);
    }

    public function branches(Request $request)
    {
        $branches = Branch::where('active', true)
            ->when($request->filled('state'), fn ($q) => $q->where('state', $request->string('state')))
            ->orderBy('state')->orderBy('city')->get();

        return view('public.branches', [
            'branches' => $branches,
            'states' => Branch::where('active', true)->distinct()->orderBy('state')->pluck('state'),
            'mapPoints' => $branches->map(fn ($b) => ['name' => $b->name, 'lat' => $b->lat, 'lng' => $b->lng, 'address' => "{$b->address}, {$b->city}", 'directions' => $b->directionsUrl()])->values(),
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
