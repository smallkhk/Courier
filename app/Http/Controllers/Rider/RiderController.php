<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\RiderAssignment;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Http\Request;

class RiderController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $profile = $user->riderProfile;
        abort_unless($profile, 403, 'No rider profile. Please contact operations.');

        return view('rider.dashboard', [
            'profile' => $profile,
            'jobs' => RiderAssignment::with('shipment.service')->where('rider_id', $user->id)->whereIn('status', ['assigned', 'accepted'])->orderBy('assigned_at')->get(),
            'doneToday' => RiderAssignment::where('rider_id', $user->id)->where('status', 'completed')->whereDate('ended_at', today())->count(),
            'locCfg' => [
                'sharing' => $profile->on_duty && $profile->location_sharing,
                'toggleUrl' => route('api.rider.sharing'),
                'postUrl' => route('api.rider.location'),
                'intervalMs' => max(15, (int) Settings::get('location_interval_seconds', 30)) * 1000,
                'staleMs' => (int) Settings::get('location_stale_minutes', 5) * 60000,
            ],
        ]);
    }

    public function history(Request $request)
    {
        return view('rider.history', [
            'rows' => RiderAssignment::with('shipment')->where('rider_id', $request->user()->id)->whereNotIn('status', ['assigned', 'accepted'])->latest('assigned_at')->paginate(25),
        ]);
    }

    public function duty(Request $request)
    {
        $request->validate(['on_duty' => 'required|boolean']);
        $profile = $request->user()->riderProfile;
        abort_unless($profile && $profile->is_active, 403);
        $on = $request->boolean('on_duty');
        $profile->forceFill(['on_duty' => $on, 'on_duty_since' => $on ? now() : null, 'location_sharing' => $on ? $profile->location_sharing : false])->save();
        Audit::log($on ? 'rider.on_duty' : 'rider.off_duty', $profile);

        return back()->with('success', $on ? 'You are on duty.' : 'You are off duty. Location sharing has stopped.');
    }
}
