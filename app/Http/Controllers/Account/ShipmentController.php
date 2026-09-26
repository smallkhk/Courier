<?php

namespace App\Http\Controllers\Account;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\ShipmentWorkflow;
use App\Support\Settings;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function index(Request $request)
    {
        $q = Shipment::where('user_id', $request->user()->id)->whereNull('business_id')->with('service');
        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $q->where(fn ($w) => $w->where('tracking_number', 'like', $term)->orWhere('recipient_name', 'like', $term)->orWhere('delivery_city', 'like', $term));
        }
        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        return view('account.shipments.index', ['shipments' => $q->latest()->paginate(15)->withQueryString()]);
    }

    public function show(Request $request, Shipment $shipment)
    {
        abort_unless($shipment->user_id === $request->user()->id && ! $shipment->business_id, 404);

        return view('account.shipments.show', [
            'shipment' => $shipment->load(['events', 'service', 'items', 'payments', 'proofs', 'attempts']),
            'canCancel' => in_array($shipment->status->value, (array) Settings::get('customer_cancel_statuses'), true) && ! $shipment->isPaid(),
        ]);
    }

    public function cancel(Request $request, Shipment $shipment, ShipmentWorkflow $workflow)
    {
        abort_unless($shipment->user_id === $request->user()->id && ! $shipment->business_id, 404);
        $request->validate(['reason' => 'nullable|string|max:200']);
        if ($shipment->isPaid()) {
            return back()->with('error', 'This shipment has been paid. Please contact support to cancel it and arrange a refund.');
        }
        if (! in_array($shipment->status->value, (array) Settings::get('customer_cancel_statuses'), true)) {
            return back()->with('error', 'This shipment can no longer be cancelled online.');
        }
        $workflow->transition($shipment, ShipmentStatus::Cancelled, $request->user(), ['public_description' => $request->input('reason') ?: 'Cancelled at the customer\'s request.']);

        return back()->with('success', 'Shipment cancelled.');
    }
}
