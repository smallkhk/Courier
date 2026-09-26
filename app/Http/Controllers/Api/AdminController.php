<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShipmentResource;
use App\Models\AuditLog;
use App\Models\RiderProfile;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ReportService;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function dashboard(ReportService $reports)
    {
        return response()->json($reports->operations());
    }

    public function shipments(Request $request)
    {
        $q = Shipment::with('service', 'events')->latest();
        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }
        if ($request->filled('q')) {
            $term = $request->string('q');
            $q->where(fn ($w) => $w->where('tracking_number', $term)->orWhere('sender_phone', $term)->orWhere('recipient_phone', $term)->orWhere('sender_email', $term));
        }

        return ShipmentResource::collection($q->paginate(50));
    }

    public function riders()
    {
        return response()->json(['data' => RiderProfile::with('user')->get()->map(fn ($p) => [
            'id' => $p->user_id, 'name' => $p->user->name, 'active' => $p->is_active, 'on_duty' => $p->on_duty,
            'open_assignments' => $p->activeAssignmentCount(), 'location_freshness' => $p->locationFreshness(),
            'last_location_at' => $p->last_location_at?->toIso8601String(),
        ])]);
    }

    public function createRider(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120', 'email' => 'required|email|unique:users,email', 'phone' => 'required|string|max:32',
            'service_zone_id' => 'nullable|exists:service_zones,id', 'vehicle_type' => 'nullable|string|max:30',
        ]);
        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'], 'role' => 'rider', 'password' => Str::password(24), 'email_verified_at' => now()]);
        $user->riderProfile()->create(['service_zone_id' => $data['service_zone_id'] ?? null, 'vehicle_type' => $data['vehicle_type'] ?? 'motorcycle']);
        Password::sendResetLink(['email' => $user->email]);
        Audit::log('admin.rider_created', $user);

        return response()->json(['id' => $user->id], 201);
    }

    public function audit(Request $request)
    {
        return response()->json(AuditLog::with('actor:id,name')->when($request->filled('action'), fn ($q) => $q->where('action', 'like', $request->string('action').'%'))->latest('id')->paginate(50));
    }
}
