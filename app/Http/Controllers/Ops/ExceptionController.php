<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAttempt;
use App\Models\Shipment;
use App\Services\DeliveryService;
use Illuminate\Http\Request;

class ExceptionController extends Controller
{
    public function index()
    {
        return view('ops.exceptions', [
            'attempts' => DeliveryAttempt::with('shipment', 'rider')->whereNull('resolution')->latest('attempted_at')->get(),
            'held' => Shipment::whereIn('status', ['on_hold', 'delivery_exception'])->latest('status_changed_at')->limit(50)->get(),
            'returns' => Shipment::whereIn('status', ['return_initiated', 'return_in_transit'])->latest('status_changed_at')->limit(50)->get(),
        ]);
    }

    public function resolve(Request $request, DeliveryAttempt $attempt, DeliveryService $delivery)
    {
        $data = $request->validate([
            'resolution' => 'required|in:'.implode(',', array_keys(DeliveryAttempt::RESOLUTIONS)),
            'retry_on' => 'nullable|required_if:resolution,retry|date|after_or_equal:today',
            'note' => 'nullable|string|max:500',
        ]);
        abort_if($attempt->resolution !== null, 422, 'Already resolved.');
        $delivery->resolveAttempt($attempt, $request->user(), $data['resolution'], $data['retry_on'] ?? null, $data['note'] ?? null);

        return back()->with('success', 'Next step recorded.');
    }
}
