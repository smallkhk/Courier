<?php

namespace App\Http\Controllers\Rider;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\DeliveryAttempt;
use App\Models\RiderAssignment;
use App\Services\DeliveryService;
use App\Services\DispatchService;
use App\Services\ShipmentWorkflow;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JobController extends Controller
{
    /** Riders may only open their own assignments. */
    private function own(Request $request, RiderAssignment $assignment): RiderAssignment
    {
        abort_unless($assignment->rider_id === $request->user()->id, 404);

        return $assignment->load('shipment.service', 'shipment.items', 'shipment.events');
    }

    public function show(Request $request, RiderAssignment $assignment)
    {
        $a = $this->own($request, $assignment);
        $s = $a->shipment;
        $next = $a->isOpen() ? collect($s->status->allowedNext('rider'))
            ->reject(fn ($st) => in_array($st, [ShipmentStatus::Delivered, ShipmentStatus::DeliveryAttempted, ShipmentStatus::DeliveryException], true)) : collect();

        return view('rider.job', [
            'a' => $a,
            's' => $s,
            'next' => $next,
            'canDeliver' => $a->isOpen() && $s->status->canTransitionTo(ShipmentStatus::Delivered, 'rider'),
            'requireSignature' => (bool) Settings::get('proof_require_signature'),
            'requirePhoto' => (bool) Settings::get('proof_require_photo'),
            'requireCode' => (bool) $s->delivery_code_hash && Settings::get('proof_require_code'),
        ]);
    }

    public function respond(Request $request, RiderAssignment $assignment, DispatchService $dispatch)
    {
        $this->own($request, $assignment);
        $request->validate(['accept' => 'required|boolean', 'reason' => 'nullable|required_if:accept,0|string|max:200']);
        $dispatch->respond($assignment, $request->user(), $request->boolean('accept'), $request->input('reason'));

        return $request->boolean('accept')
            ? back()->with('success', 'Job accepted.')
            : redirect()->route('rider.dashboard')->with('status', 'Job declined. Operations has been notified.');
    }

    public function status(Request $request, RiderAssignment $assignment, ShipmentWorkflow $workflow)
    {
        $a = $this->own($request, $assignment);
        abort_unless($a->isOpen(), 422, 'This job is closed.');
        $data = $request->validate([
            'status' => ['required', Rule::enum(ShipmentStatus::class)],
            'note' => 'nullable|string|max:500',
            'location' => 'nullable|string|max:120',
        ]);
        $to = ShipmentStatus::from($data['status']);
        abort_if(in_array($to, [ShipmentStatus::Delivered, ShipmentStatus::DeliveryAttempted], true), 422);
        if ($a->status === 'assigned') {
            $a->forceFill(['status' => 'accepted', 'responded_at' => now()])->save();
        }
        $workflow->transition($a->shipment, $to, $request->user(), ['internal_note' => $data['note'] ?? null, 'location' => $data['location'] ?? null]);
        if ($to === ShipmentStatus::AtOriginFacility || $to === ShipmentStatus::AtDestinationFacility) {
            // Handed over at a hub: this rider's leg is complete.
            $a->forceFill(['status' => 'completed', 'ended_at' => now()])->save();

            return redirect()->route('rider.dashboard')->with('success', 'Handed over at hub. Job complete.');
        }

        return back()->with('success', 'Status updated to “'.$to->label().'”.');
    }

    public function proof(Request $request, RiderAssignment $assignment, DeliveryService $delivery)
    {
        $a = $this->own($request, $assignment);
        $data = $request->validate([
            'recipient_name' => 'required|string|max:120',
            'note' => 'nullable|string|max:500',
            'signature' => 'nullable|string|max:700000',
            'photo' => 'nullable|file|max:'.config('courier.uploads.max_kb').'|mimes:jpg,jpeg,png,webp',
            'delivery_code' => 'nullable|digits:6',
            'cod_amount_collected' => 'nullable|numeric|min:0',
        ]);
        $delivery->submitProof($a->shipment, $request->user(), $data + [
            'photo' => $request->file('photo'),
            'photo_consent' => $request->boolean('photo_consent'),
        ]);

        return redirect()->route('rider.dashboard')->with('success', 'Delivered — proof saved for '.$a->shipment->tracking_number.'.');
    }

    public function failed(Request $request, RiderAssignment $assignment, DeliveryService $delivery)
    {
        $a = $this->own($request, $assignment);
        $data = $request->validate([
            'reason' => ['required', Rule::in(array_keys(DeliveryAttempt::REASONS))],
            'note' => 'nullable|string|max:250',
            'evidence' => 'nullable|file|max:'.config('courier.uploads.max_kb').'|mimes:jpg,jpeg,png,webp',
        ]);
        $delivery->recordFailedAttempt($a->shipment, $request->user(), $data + ['evidence' => $request->file('evidence')]);

        return back()->with('status', 'Failed attempt recorded. Operations will decide the next step.');
    }
}
