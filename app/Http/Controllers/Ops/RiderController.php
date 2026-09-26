<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\RiderAssignment;
use App\Models\RiderProfile;
use App\Models\ServiceZone;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class RiderController extends Controller
{
    public function index()
    {
        return view('ops.riders.index', ['riders' => RiderProfile::with('user', 'zone')->get()->sortBy('user.name')]);
    }

    public function create()
    {
        return view('ops.riders.form', ['zones' => ServiceZone::orderBy('name')->pluck('name', 'id'), 'profile' => null]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190|unique:users,email',
            'phone' => 'required|string|max:32',
            'service_zone_id' => 'nullable|exists:service_zones,id',
            'vehicle_type' => 'required|string|max:30',
            'plate_number' => 'nullable|string|max:30',
            'max_active_assignments' => 'required|integer|min:1|max:100',
        ]);
        $user = DB::transaction(function () use ($data) {
            $user = User::create(['name' => $data['name'], 'email' => strtolower($data['email']), 'phone' => $data['phone'], 'role' => 'rider', 'password' => Str::password(32), 'email_verified_at' => now()]);
            $user->riderProfile()->create(collect($data)->only(['service_zone_id', 'vehicle_type', 'plate_number', 'max_active_assignments'])->all());

            return $user;
        });
        Password::sendResetLink(['email' => $user->email]);
        Audit::log('admin.rider_created', $user);

        return redirect()->route('ops.riders.show', $user)->with('success', 'Rider created. They will receive an email to set their password.');
    }

    public function show(User $user)
    {
        abort_unless($user->isRole('rider') && $user->riderProfile, 404);

        return view('ops.riders.form', [
            'profile' => $user->riderProfile->load('user'),
            'zones' => ServiceZone::orderBy('name')->pluck('name', 'id'),
            'jobs' => RiderAssignment::with('shipment')->where('rider_id', $user->id)->latest('assigned_at')->limit(30)->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        abort_unless($user->isRole('rider') && $user->riderProfile, 404);
        $data = $request->validate([
            'phone' => 'required|string|max:32',
            'service_zone_id' => 'nullable|exists:service_zones,id',
            'vehicle_type' => 'required|string|max:30',
            'plate_number' => 'nullable|string|max:30',
            'max_active_assignments' => 'required|integer|min:1|max:100',
            'is_active' => 'nullable|boolean',
        ]);
        $user->update(['phone' => $data['phone']]);
        $active = $request->boolean('is_active');
        $user->riderProfile->update(collect($data)->only(['service_zone_id', 'vehicle_type', 'plate_number', 'max_active_assignments'])->all() + [
            'is_active' => $active,
            'on_duty' => $active ? $user->riderProfile->on_duty : false,
            'location_sharing' => $active ? $user->riderProfile->location_sharing : false,
        ]);
        Audit::log('admin.rider_updated', $user, ['is_active' => $active]);

        return back()->with('success', 'Rider updated.');
    }
}
