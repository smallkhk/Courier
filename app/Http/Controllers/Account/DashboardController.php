<?php

namespace App\Http\Controllers\Account;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Shipment;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $base = Shipment::where('user_id', $user->id)->whereNull('business_id');
        $active = (clone $base)->whereNotIn('status', ['delivered', 'returned_to_sender', 'cancelled', 'draft'])->count();

        return view('account.dashboard', [
            'recent' => (clone $base)->latest()->limit(6)->get(),
            'counts' => [
                'active' => $active,
                'awaiting_payment' => (clone $base)->where('status', ShipmentStatus::PendingPayment)->count(),
                'delivered' => (clone $base)->where('status', ShipmentStatus::Delivered)->count(),
                'total' => (clone $base)->count(),
            ],
        ]);
    }
}
