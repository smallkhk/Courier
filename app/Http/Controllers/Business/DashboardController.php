<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $m = $request->attributes->get('membership');
        $business = $m->business;
        if (! $business->isApproved()) {
            return view('business.pending', ['business' => $business]);
        }
        $scope = Shipment::visibleTo($request->user())->where('business_id', $business->id);
        $count = fn (array $st) => (clone $scope)->whereIn('status', $st)->count();

        return view('business.dashboard', [
            'business' => $business,
            'membership' => $m,
            'stats' => [
                'awaiting_payment' => $count(['pending_payment']),
                'in_progress' => $count(['booked', 'pickup_scheduled', 'rider_assigned', 'picked_up', 'at_origin_facility', 'in_transit', 'at_destination_facility', 'out_for_delivery']),
                'exceptions' => $count(['delivery_attempted', 'delivery_exception', 'on_hold', 'return_initiated', 'return_in_transit']),
                'delivered_30d' => (clone $scope)->where('status', 'delivered')->where('delivered_at', '>=', now()->subDays(30))->count(),
            ],
            'recent' => (clone $scope)->with('service')->latest()->limit(8)->get(),
        ]);
    }
}
