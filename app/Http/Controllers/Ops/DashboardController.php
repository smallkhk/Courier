<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAttempt;
use App\Models\RiderProfile;
use App\Models\ShipmentEvent;
use App\Services\DispatchService;
use App\Services\ReportService;

class DashboardController extends Controller
{
    public function index(ReportService $reports, DispatchService $dispatch)
    {
        return view('ops.dashboard', [
            'ops' => $reports->operations(),
            'unassigned' => $dispatch->unassignedQuery()->with('service')->oldest('status_changed_at')->limit(8)->get(),
            'attempts' => DeliveryAttempt::with('shipment')->whereNull('resolution')->latest('attempted_at')->limit(6)->get(),
            'riders' => RiderProfile::with('user')->where('is_active', true)->get()->sortByDesc('on_duty'),
            'activity' => ShipmentEvent::with('shipment:id,tracking_number')->latest('occurred_at')->latest('id')->limit(8)->get(),
        ]);
    }
}
