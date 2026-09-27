<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\Payments\PaymentService;
use App\Support\Csv;
use App\Support\Units;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShipmentController extends Controller
{
    private function query(Request $request)
    {
        $business = $request->attributes->get('membership')->business;
        $q = Shipment::visibleTo($request->user())->where('business_id', $business->id)->with('service');
        if ($request->filled('q')) {
            $t = '%'.$request->string('q').'%';
            $q->where(fn ($w) => $w->where('tracking_number', 'like', $t)->orWhere('recipient_name', 'like', $t)->orWhere('recipient_phone', 'like', $t)->orWhere('delivery_city', 'like', $t)->orWhere('delivery_instructions', 'like', $t));
        }
        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }
        if ($request->filled('from')) {
            $q->whereDate('created_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $q->whereDate('created_at', '<=', $request->date('to'));
        }
        $sort = in_array($request->input('sort'), ['created_at', 'total', 'status'], true) ? $request->input('sort') : 'created_at';

        return $q->orderBy($sort, $request->input('dir') === 'asc' ? 'asc' : 'desc');
    }

    public function index(Request $request)
    {
        return view('business.shipments.index', [
            'shipments' => $this->query($request)->paginate(25)->withQueryString(),
            'membership' => $request->attributes->get('membership'),
        ]);
    }

    public function show(Request $request, Shipment $shipment)
    {
        $m = $request->attributes->get('membership');
        abort_unless($shipment->business_id === $m->business_id && Shipment::visibleTo($request->user())->whereKey($shipment->id)->exists(), 404);

        return view('business.shipments.show', ['shipment' => $shipment->load(['events', 'service', 'items', 'payments', 'proofs'])]);
    }

    public function export(Request $request)
    {
        $rows = $this->query($request)->limit(10000)->get()->map(fn ($s) => [
            $s->tracking_number, $s->created_at->toDateTimeString(), $s->status->label(), $s->service->name,
            $s->recipient_name, $s->delivery_city, $s->delivery_region, $s->delivery_postal_code, $s->delivery_country, $s->parcel_count, round((float) $s->chargeable_weight_kg / Units::LB_TO_KG, 2), $s->chargeable_weight_kg,
            $s->total, $s->currency, $s->payment_method, $s->delivered_at?->toDateTimeString(),
        ]);

        return Csv::download('shipments-'.now()->format('Ymd-His').'.csv',
            ['Tracking number', 'Created', 'Status', 'Service', 'Recipient', 'City', 'State/region', 'Postal code', 'Country', 'Parcels', 'Chargeable lb', 'Chargeable kg', 'Total', 'Currency', 'Payment method', 'Delivered at'], $rows);
    }

    /** Pay several pending shipments in one checkout. */
    public function payMany(Request $request, PaymentService $payments)
    {
        $request->validate(['shipments' => 'required|array|min:1|max:200', 'shipments.*' => 'string']);
        $m = $request->attributes->get('membership');
        $shipments = Shipment::visibleTo($request->user())->where('business_id', $m->business_id)
            ->whereIn('tracking_number', $request->input('shipments'))->get();
        try {
            $payment = $payments->start($shipments, $request->user()->email, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->away($payment->checkout_url);
    }
}
