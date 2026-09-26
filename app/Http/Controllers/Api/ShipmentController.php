<?php

namespace App\Http\Controllers\Api;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\Controller;
use App\Http\Resources\ShipmentResource;
use App\Models\Quote;
use App\Models\Shipment;
use App\Services\BookingService;
use App\Services\ShipmentWorkflow;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShipmentController extends Controller
{
    public function index(Request $request)
    {
        $q = Shipment::visibleTo($request->user())->with('service')->latest();
        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        return ShipmentResource::collection($q->paginate(min(100, (int) $request->input('per_page', 20))));
    }

    /**
     * Book from a quote. Body: all booking detail fields + quote_id + payment_method + idempotency_key + accept_terms.
     * The price comes from the stored quote; details must match the quote's inputs.
     */
    public function store(Request $request, BookingService $booking)
    {
        $d = $request->validate(BookingController::detailRules() + [
            'quote_id' => 'required|uuid',
            'payment_method' => 'required|in:online,cod,invoice',
            'idempotency_key' => 'required|string|max:64',
            'accept_terms' => 'accepted',
        ]);
        BookingController::assertAddressesInZones($d);
        $quote = Quote::findOrFail($d['quote_id']);
        $input = BookingController::quoteInput($d);
        foreach (['service_id', 'origin_zone_id', 'destination_zone_id'] as $k) {
            if ((int) $quote->inputs[$k] !== (int) $input[$k]) {
                throw ValidationException::withMessages(['quote_id' => 'The quote does not match these shipment details. Request a new quote.']);
            }
        }
        if (json_encode($quote->inputs['parcels']) !== json_encode($input['parcels']) || (bool) ($quote->inputs['insured'] ?? false) !== $input['insured'] || (bool) ($quote->inputs['pickup_requested'] ?? false) !== $input['pickup_requested']) {
            throw ValidationException::withMessages(['quote_id' => 'Parcel details changed since the quote. Request a new quote.']);
        }

        $result = $booking->create($d, $quote, $request->user(), BookingController::bookingBusiness($request), $d['payment_method'], $d['idempotency_key']);

        return (new ShipmentResource($result['shipment']->load('service', 'events')))->response()->setStatusCode($result['created'] ? 201 : 200);
    }

    public function show(Request $request, Shipment $shipment)
    {
        abort_unless(Shipment::visibleTo($request->user())->whereKey($shipment->id)->exists(), 404);

        return new ShipmentResource($shipment->load('service', 'events'));
    }

    public function cancel(Request $request, Shipment $shipment, ShipmentWorkflow $workflow)
    {
        $user = $request->user();
        abort_unless(Shipment::visibleTo($user)->whereKey($shipment->id)->exists(), 404);
        $request->validate(['reason' => 'nullable|string|max:200']);
        if (! $user->isStaff() && ! in_array($shipment->status->value, (array) Settings::get('customer_cancel_statuses'), true)) {
            throw ValidationException::withMessages(['status' => 'This shipment can no longer be cancelled online. Please contact support.']);
        }
        if ($shipment->isPaid() && ! $user->isStaff()) {
            throw ValidationException::withMessages(['status' => 'This shipment is paid. Please contact support to cancel and arrange a refund.']);
        }
        $workflow->transition($shipment, ShipmentStatus::Cancelled, $user, ['public_description' => $request->input('reason') ?: 'Cancelled at the customer\'s request.']);

        return new ShipmentResource($shipment->fresh()->load('service', 'events'));
    }
}
