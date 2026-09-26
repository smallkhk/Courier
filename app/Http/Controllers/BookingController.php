<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Business;
use App\Models\Quote;
use App\Models\Service;
use App\Models\ServiceZone;
use App\Models\Shipment;
use App\Services\BookingService;
use App\Services\PricingService;
use App\Support\Settings;
use App\Support\ShipmentAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Guided booking: details → server quote → review → confirm (→ checkout).
 * Details and quote id live in the session so an interrupted booking can be resumed.
 */
class BookingController extends Controller
{
    public static function detailRules(): array
    {
        $phone = ['required', 'string', 'regex:/^\+?[0-9 ()-]{7,20}$/'];

        return [
            'sender_name' => 'required|string|max:120',
            'sender_phone' => $phone,
            'sender_email' => 'required|email:rfc|max:190',
            'pickup_address' => 'required|string|max:250',
            'pickup_city' => 'required|string|max:100',
            'pickup_state' => 'required|string|max:100',
            'origin_zone_id' => 'required|integer|exists:service_zones,id',
            'pickup_instructions' => 'nullable|string|max:500',
            'recipient_name' => 'required|string|max:120',
            'recipient_phone' => $phone,
            'recipient_email' => 'nullable|email:rfc|max:190',
            'delivery_address' => 'required|string|max:250',
            'delivery_city' => 'required|string|max:100',
            'delivery_state' => 'required|string|max:100',
            'destination_zone_id' => 'required|integer|exists:service_zones,id',
            'delivery_instructions' => 'nullable|string|max:500',
            'package_description' => 'required|string|max:200',
            'package_category' => ['required', Rule::in(array_keys(Shipment::CATEGORIES))],
            'parcels' => 'required|array|min:1|max:20',
            'parcels.*.weight_kg' => 'required|numeric|min:0.01|max:1000',
            'parcels.*.length_cm' => 'nullable|numeric|min:1|max:500',
            'parcels.*.width_cm' => 'nullable|numeric|min:1|max:500',
            'parcels.*.height_cm' => 'nullable|numeric|min:1|max:500',
            'service_id' => 'required|integer|exists:services,id',
            'declared_value' => 'nullable|numeric|min:0|max:100000000',
            'insured' => 'nullable|boolean',
            'special_handling' => 'nullable|array',
            'special_handling.*' => Rule::in(array_keys(Shipment::HANDLING)),
            'pickup_requested' => 'nullable|boolean',
            'pickup_date' => 'nullable|required_if:pickup_requested,1|date|after_or_equal:today|before:+30 days',
            'pickup_window' => ['nullable', Rule::in(array_keys(Shipment::PICKUP_WINDOWS))],
        ];
    }

    /** Validate that addresses fall inside the chosen zones. */
    public static function assertAddressesInZones(array $d): void
    {
        $errors = [];
        $origin = ServiceZone::find($d['origin_zone_id']);
        $dest = ServiceZone::find($d['destination_zone_id']);
        if ($origin && ! $origin->coversCity($d['pickup_city'])) {
            $errors['pickup_city'] = "“{$d['pickup_city']}” is not in the {$origin->name} coverage area. Check the city or choose another area.";
        }
        if ($dest && ! $dest->coversCity($d['delivery_city'])) {
            $errors['delivery_city'] = "“{$d['delivery_city']}” is not in the {$dest->name} coverage area. Check the city or choose another area.";
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    public static function quoteInput(array $d): array
    {
        return [
            'service_id' => (int) $d['service_id'],
            'origin_zone_id' => (int) $d['origin_zone_id'],
            'destination_zone_id' => (int) $d['destination_zone_id'],
            'parcels' => array_values(array_map(fn ($p) => array_filter([
                'weight_kg' => $p['weight_kg'],
                'length_cm' => $p['length_cm'] ?? null,
                'width_cm' => $p['width_cm'] ?? null,
                'height_cm' => $p['height_cm'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''), $d['parcels'])),
            'declared_value' => $d['declared_value'] ?? 0,
            'insured' => (bool) ($d['insured'] ?? false),
            'pickup_requested' => (bool) ($d['pickup_requested'] ?? false),
        ];
    }

    /** The business the current user books for, if any (approved, with create permission). */
    public static function bookingBusiness(Request $request): ?Business
    {
        $m = $request->user()?->primaryMembership();

        return $m && $m->business->isApproved() && $m->can('create_shipments') && $request->session()->get('booking.account', 'business') === 'business'
            ? $m->business : null;
    }

    public function start(Request $request)
    {
        if (! $request->user() && ! Settings::get('guest_booking_enabled')) {
            return redirect()->route('login')->with('status', 'Please sign in to send a parcel.');
        }
        if ($request->filled('as')) {
            $request->session()->put('booking.account', $request->query('as') === 'personal' ? 'personal' : 'business');
        }
        $user = $request->user();
        $business = self::bookingBusiness($request);
        $addresses = $business
            ? Address::where('business_id', $business->id)->get()
            : ($user ? $user->addresses()->get() : collect());

        $details = $request->session()->get('booking.details', []);
        if (! $details && $user) {
            $details = ['sender_name' => $user->name, 'sender_phone' => $user->phone, 'sender_email' => $user->email];
        }

        return view('booking.start', [
            'zones' => ServiceZone::where('active', true)->orderBy('state')->orderBy('name')->get(),
            'services' => Service::where('active', true)->orderBy('sort_order')->get(),
            'addresses' => $addresses,
            'details' => $details,
            'business' => $business,
            'membership' => $user?->primaryMembership(),
        ]);
    }

    public function quote(Request $request, PricingService $pricing)
    {
        $d = $request->validate(self::detailRules());
        $d['insured'] = $request->boolean('insured');
        $d['pickup_requested'] = $request->boolean('pickup_requested');
        $request->session()->put('booking.details', $d);
        self::assertAddressesInZones($d);

        $quote = $pricing->quote(self::quoteInput($d), $request->user()?->id, self::bookingBusiness($request));
        $request->session()->put('booking.quote_id', $quote->id);
        $request->session()->put('booking.idempotency', (string) Str::uuid());

        return redirect()->route('book.review');
    }

    public function review(Request $request, PricingService $pricing)
    {
        $d = $request->session()->get('booking.details');
        $quote = Quote::with('service')->find($request->session()->get('booking.quote_id'));
        if (! $d || ! $quote) {
            return redirect()->route('book.start')->with('status', 'Start by entering your shipment details.');
        }
        $priceChanged = false;
        if ($quote->isExpired()) {
            // Quotes expire; re-price from the same details and tell the customer.
            $old = $quote->total;
            try {
                $quote = $pricing->quote(self::quoteInput($d), $request->user()?->id, self::bookingBusiness($request))->load('service');
            } catch (ValidationException $e) {
                return redirect()->route('book.start')->withErrors($e->errors());
            }
            $request->session()->put('booking.quote_id', $quote->id);
            $request->session()->put('booking.idempotency', (string) Str::uuid());
            $priceChanged = $old !== $quote->total;
        }

        return view('booking.review', [
            'd' => $d,
            'quote' => $quote,
            'priceChanged' => $priceChanged,
            'origin' => ServiceZone::find($d['origin_zone_id']),
            'destination' => ServiceZone::find($d['destination_zone_id']),
            'business' => self::bookingBusiness($request),
            'codEnabled' => (bool) Settings::get('cod_enabled'),
            'idempotency' => $request->session()->get('booking.idempotency'),
        ]);
    }

    public function confirm(Request $request, BookingService $booking)
    {
        $request->validate([
            'accept_terms' => 'accepted',
            'payment_method' => 'required|in:online,cod,invoice',
            'idempotency_key' => 'required|uuid',
        ], ['accept_terms.accepted' => 'Please accept the terms and delivery policy to continue.']);

        $d = $request->session()->get('booking.details');
        $quote = Quote::find($request->session()->get('booking.quote_id'));
        if (! $d || ! $quote) {
            // Session gone but the booking may already exist (double submit after success).
            $existing = Shipment::where('idempotency_key', $request->input('idempotency_key'))->first();
            if ($existing && ShipmentAccess::canManage($request, $existing)) {
                return redirect()->route($existing->isPayable() ? 'checkout.show' : 'book.confirmation', $existing);
            }

            return redirect()->route('book.start')->with('status', 'Your booking session expired. Please re-enter the details.');
        }
        if ($quote->isExpired()) {
            return redirect()->route('book.review')->with('warning', 'Your quote expired, so we re-checked the price. Please review and confirm again.');
        }

        $result = $booking->create($d, $quote, $request->user(), self::bookingBusiness($request), $request->input('payment_method'), $request->input('idempotency_key'));
        $shipment = $result['shipment'];
        if ($result['guest_token']) {
            ShipmentAccess::rememberGuest($request, $shipment, $result['guest_token']);
        }
        $request->session()->forget(['booking.details', 'booking.quote_id', 'booking.idempotency']);

        return $shipment->isPayable()
            ? redirect()->route('checkout.show', $shipment)
            : redirect()->route('book.confirmation', $shipment)->with('success', 'Your shipment is booked.');
    }

    public function confirmation(Request $request, Shipment $shipment)
    {
        ShipmentAccess::authorize($request, $shipment);

        return view('booking.confirmation', ['shipment' => $shipment->load('service', 'payments')]);
    }
}
