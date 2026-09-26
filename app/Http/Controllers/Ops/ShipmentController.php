<?php

namespace App\Http\Controllers\Ops;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceZone;
use App\Models\Shipment;
use App\Models\User;
use App\Services\DeliveryService;
use App\Services\DispatchService;
use App\Services\ShipmentWorkflow;
use App\Support\Audit;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShipmentController extends Controller
{
    private function query(Request $request)
    {
        $q = Shipment::with(['service', 'user', 'business', 'activeAssignment.rider']);
        if ($request->filled('q')) {
            $raw = trim($request->string('q'));
            $like = '%'.$raw.'%';
            $q->where(fn ($w) => $w->where('tracking_number', $raw)->orWhere('tracking_number', 'like', $like)
                ->orWhere('sender_phone', 'like', $like)->orWhere('recipient_phone', 'like', $like)
                ->orWhere('sender_email', 'like', $like)->orWhere('recipient_email', 'like', $like)
                ->orWhere('sender_name', 'like', $like)->orWhere('recipient_name', 'like', $like));
        }
        foreach (['status', 'payment_method', 'service_id', 'destination_zone_id', 'business_id'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->input($f));
            }
        }
        if ($request->filled('from')) {
            $q->whereDate('created_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $q->whereDate('created_at', '<=', $request->date('to'));
        }
        $sort = in_array($request->input('sort'), ['created_at', 'total', 'status', 'status_changed_at'], true) ? $request->input('sort') : 'created_at';

        return $q->orderBy($sort, $request->input('dir') === 'asc' ? 'asc' : 'desc');
    }

    public function index(Request $request)
    {
        if ($request->filled('q')) {
            Audit::log('ops.shipment_search', null, ['q' => mb_substr($request->string('q'), 0, 60)]);
        }

        return view('ops.shipments.index', [
            'shipments' => $this->query($request)->paginate(30)->withQueryString(),
            'services' => Service::orderBy('name')->pluck('name', 'id'),
            'zones' => ServiceZone::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function export(Request $request)
    {
        // Operational export: no payment secrets, no internal notes, contact details only for staff.
        Audit::log('ops.shipments_exported', null, ['filters' => $request->except('_token')]);
        $rows = $this->query($request)->limit(20000)->get()->map(fn ($s) => [
            $s->tracking_number, $s->created_at->toDateTimeString(), $s->status->value, $s->service->name,
            $s->business?->name ?? $s->user?->name ?? 'Guest', $s->sender_phone, $s->pickup_city,
            $s->recipient_name, $s->recipient_phone, $s->delivery_city, $s->payment_method, $s->total, $s->currency,
            $s->activeAssignment?->rider?->name, $s->delivered_at?->toDateTimeString(),
        ]);

        return Csv::download('ops-shipments-'.now()->format('Ymd-His').'.csv', [
            'Tracking', 'Created', 'Status', 'Service', 'Customer', 'Sender phone', 'Pickup city', 'Recipient', 'Recipient phone', 'Delivery city', 'Payment', 'Total', 'Currency', 'Rider', 'Delivered at',
        ], $rows);
    }

    public function show(Request $request, Shipment $shipment, DispatchService $dispatch)
    {
        $role = ShipmentWorkflow::roleFor($request->user());

        return view('ops.shipments.show', [
            's' => $shipment->load(['events.actor', 'service', 'items', 'payments', 'proofs.submitter', 'attempts.rider', 'assignments.rider', 'assignments.assigner', 'user', 'business', 'codCollection', 'originZone', 'destinationZone']),
            'next' => collect($shipment->status->allowedNext($role))->reject(fn ($s) => in_array($s, [ShipmentStatus::Delivered, ShipmentStatus::DeliveryAttempted], true)),
            'suggested' => $shipment->status->isTerminal() ? collect() : $dispatch->suggestRiders($shipment, in_array($shipment->status->value, ['booked', 'pickup_scheduled'], true) ? 'pickup' : 'delivery'),
            'canDeliver' => $shipment->status->canTransitionTo(ShipmentStatus::Delivered, $role),
        ]);
    }

    public function status(Request $request, Shipment $shipment, ShipmentWorkflow $workflow)
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(ShipmentStatus::class)],
            'public_description' => 'nullable|string|max:250',
            'internal_note' => 'nullable|string|max:1000',
            'location' => 'nullable|string|max:120',
        ]);
        $to = ShipmentStatus::from($data['status']);
        abort_if($to === ShipmentStatus::Delivered, 422, 'Use the proof-of-delivery form.');
        $workflow->transition($shipment, $to, $request->user(), $data);

        return back()->with('success', 'Status changed to “'.$to->label().'”.');
    }

    public function assign(Request $request, Shipment $shipment, DispatchService $dispatch)
    {
        $data = $request->validate(['rider_id' => 'required|integer|exists:users,id', 'leg' => 'required|in:pickup,delivery', 'note' => 'nullable|string|max:250']);
        $rider = User::findOrFail($data['rider_id']);
        $dispatch->assign($shipment, $rider, $request->user(), $data['leg'], $data['note'] ?? null);

        return back()->with('success', "Assigned to {$rider->name}.");
    }

    public function note(Request $request, Shipment $shipment, ShipmentWorkflow $workflow)
    {
        $data = $request->validate(['internal_note' => 'required|string|max:1000', 'public_description' => 'nullable|string|max:250']);
        $workflow->note($shipment, $data['public_description'] ?: 'Shipment details reviewed by our team.', $request->user(), $request->user()->role, $data['internal_note']);
        Audit::log('shipment.note_added', $shipment);

        return back()->with('success', 'Note added.');
    }

    /** Staff-recorded proof (e.g. customer collected at a branch). */
    public function proof(Request $request, Shipment $shipment, DeliveryService $delivery)
    {
        $data = $request->validate(['recipient_name' => 'required|string|max:120', 'note' => 'required|string|max:500', 'cod_amount_collected' => 'nullable|numeric|min:0']);
        $delivery->submitProof($shipment, $request->user(), $data);

        return back()->with('success', 'Marked delivered with proof.');
    }

    /**
     * Admin-only: confirm a booking whose payment was received outside the online gateway
     * (e.g. bank transfer to the company account). Recorded in the audit log.
     */
    public function confirmOffline(Request $request, Shipment $shipment, ShipmentWorkflow $workflow)
    {
        $data = $request->validate(['reference' => 'required|string|max:100', 'note' => 'required|string|max:500']);
        abort_unless($shipment->status === ShipmentStatus::PendingPayment, 422, 'Shipment is not awaiting payment.');
        $workflow->transition($shipment, ShipmentStatus::Booked, $request->user(), [
            'public_description' => 'Payment received. Shipment confirmed and booked.',
            'internal_note' => "Offline payment ref {$data['reference']}: {$data['note']}",
        ]);
        Audit::log('payment.offline_confirmed', $shipment, $data);

        return back()->with('success', 'Booking confirmed against an offline payment.');
    }
}
