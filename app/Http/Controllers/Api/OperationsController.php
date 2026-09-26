<?php

namespace App\Http\Controllers\Api;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ShipmentResource;
use App\Models\DeliveryAttempt;
use App\Models\Shipment;
use App\Models\User;
use App\Services\DeliveryService;
use App\Services\DispatchService;
use App\Services\ShipmentWorkflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OperationsController extends Controller
{
    /** Add a status event. Role and state-machine rules are enforced by ShipmentWorkflow. */
    public function event(Request $request, Shipment $shipment, ShipmentWorkflow $workflow)
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(ShipmentStatus::class)],
            'public_description' => 'nullable|string|max:250',
            'internal_note' => 'nullable|string|max:1000',
            'location' => 'nullable|string|max:120',
        ]);
        $status = ShipmentStatus::from($data['status']);
        abort_if(in_array($status, [ShipmentStatus::Delivered, ShipmentStatus::DeliveryAttempted], true), 422, 'Use the proof-of-delivery or delivery-attempts endpoint for this status.');
        $workflow->transition($shipment, $status, $request->user(), $data);

        return new ShipmentResource($shipment->fresh()->load('events', 'service'));
    }

    public function attempt(Request $request, Shipment $shipment, DeliveryService $delivery)
    {
        $data = $request->validate([
            'reason' => ['required', Rule::in(array_keys(DeliveryAttempt::REASONS))],
            'note' => 'nullable|string|max:250',
            'evidence' => 'nullable|file|max:'.config('courier.uploads.max_kb').'|mimes:jpg,jpeg,png,webp',
        ]);
        $attempt = $delivery->recordFailedAttempt($shipment, $request->user(), $data + ['evidence' => $request->file('evidence')]);

        return response()->json(['id' => $attempt->id, 'status' => $shipment->fresh()->status->value], 201);
    }

    public function proof(Request $request, Shipment $shipment, DeliveryService $delivery)
    {
        $data = $request->validate([
            'recipient_name' => 'required|string|max:120',
            'note' => 'nullable|string|max:500',
            'signature' => 'nullable|string|max:700000',
            'photo' => 'nullable|file|max:'.config('courier.uploads.max_kb').'|mimes:jpg,jpeg,png,webp',
            'photo_consent' => 'nullable|boolean',
            'delivery_code' => 'nullable|digits:6',
            'cod_amount_collected' => 'nullable|numeric|min:0',
        ]);
        $proof = $delivery->submitProof($shipment, $request->user(), $data + ['photo' => $request->file('photo'), 'photo_consent' => $request->boolean('photo_consent')]);

        return response()->json(['proof_id' => $proof->id, 'status' => $shipment->fresh()->status->value], 201);
    }

    public function assign(Request $request, Shipment $shipment, DispatchService $dispatch)
    {
        $data = $request->validate(['rider_id' => 'required|integer|exists:users,id', 'leg' => 'nullable|in:pickup,delivery', 'note' => 'nullable|string|max:250']);
        $a = $dispatch->assign($shipment, User::findOrFail($data['rider_id']), $request->user(), $data['leg'] ?? 'delivery', $data['note'] ?? null);

        return response()->json(['assignment_id' => $a->id, 'rider_id' => $a->rider_id, 'status' => $a->status], 201);
    }
}
