<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Business;
use App\Models\Quote;
use App\Models\Service;
use App\Models\Shipment;
use App\Services\BookingService;
use App\Services\PricingService;
use App\Services\ShipmentDetails;
use App\Support\Settings;
use App\Support\ShipmentAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Guided booking: details → server quote → review → confirm (→ checkout).
 * Details and quote id live in the session so an interrupted booking can be resumed.
 */
class BookingController extends Controller
{
    /** Validation rules for booking details (shared with the API). */
    public static function detailRules(): array
    {
        return ShipmentDetails::rules();
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

        $details = $request->session()->get('booking.form', []);
        if (! $details && $user) {
            $details = ['sender_name' => $user->name, 'sender_phone' => $user->phone, 'sender_email' => $user->email];
        }

        return view('booking.start', [
            'services' => Service::where('active', true)->orderBy('sort_order')->get(),
            'addresses' => $addresses->map(fn ($a) => ['id' => $a->id, 'label' => $a->label] + $a->toPicker())->values(),
            'details' => $details,
            'business' => $business,
            'membership' => $user?->primaryMembership(),
        ]);
    }

    public function quote(Request $request, PricingService $pricing, ShipmentDetails $details)
    {
        $d = $request->validate(self::detailRules());
        $d['insured'] = $request->boolean('insured');
        $d['pickup_requested'] = $request->boolean('pickup_requested');
        $request->session()->put('booking.form', $d); // raw input, to refill the form
        $d = $details->normalize($d);
        $request->session()->put('booking.details', $d);

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
        $request->session()->forget(['booking.details', 'booking.form', 'booking.quote_id', 'booking.idempotency']);

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
