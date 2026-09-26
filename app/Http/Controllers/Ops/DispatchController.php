<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\RiderAssignment;
use App\Models\RiderProfile;
use App\Models\ServiceZone;
use App\Services\DispatchService;
use App\Support\Settings;
use Illuminate\Http\Request;

class DispatchController extends Controller
{
    public function index(Request $request, DispatchService $dispatch)
    {
        $riders = RiderProfile::with('user', 'zone')->where('is_active', true)->get()
            ->map(function ($p) {
                $p->open = RiderAssignment::with('shipment')->where('rider_id', $p->user_id)->whereIn('status', ['assigned', 'accepted'])->get();

                return $p;
            })->sortBy(fn ($p) => [! $p->on_duty, $p->open->count()]);

        return view('ops.dispatch', [
            'unassigned' => $dispatch->unassignedQuery()->with('service', 'originZone', 'destinationZone')
                ->when($request->filled('zone'), fn ($q) => $q->where('destination_zone_id', $request->integer('zone')))
                ->oldest('status_changed_at')->paginate(30)->withQueryString(),
            'riders' => $riders,
            'zones' => ServiceZone::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function map()
    {
        $center = ServiceZone::whereNotNull('center_lat')->first();

        return view('ops.map', ['cfg' => [
            'tileUrl' => config('courier.maps.tile_url'),
            'attribution' => config('courier.maps.attribution'),
            'center' => $center ? [(float) $center->center_lat, (float) $center->center_lng] : null,
        ], 'staleMinutes' => Settings::get('location_stale_minutes')]);
    }

    /** Last-known rider positions (staff only). */
    public function mapData()
    {
        return response()->json(['riders' => RiderProfile::with('user')->where('is_active', true)->get()->map(fn ($p) => [
            'id' => $p->user_id,
            'name' => $p->user->name,
            'on_duty' => $p->on_duty,
            'sharing' => $p->location_sharing,
            'freshness' => $p->locationFreshness(),
            'lat' => $p->last_lat,
            'lng' => $p->last_lng,
            'accuracy_m' => $p->last_accuracy_m,
            'last_seen' => $p->last_location_at?->toIso8601String(),
            'last_seen_human' => $p->last_location_at?->diffForHumans() ?? 'never',
            'open_jobs' => $p->activeAssignmentCount(),
        ])->values()]);
    }
}
