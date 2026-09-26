<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\BusinessMember;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    private function assignable(BusinessMember $me): array
    {
        // Only owners can create other owners or admins.
        return $me->role === 'owner' ? array_keys(BusinessMember::PERMISSIONS) : ['shipper', 'finance', 'viewer'];
    }

    public function index(Request $request)
    {
        $me = $request->attributes->get('membership');

        return view('business.team', [
            'members' => BusinessMember::with('user')->where('business_id', $me->business_id)->get(),
            'me' => $me,
            'roles' => array_intersect_key(BusinessMember::ROLE_LABELS, array_flip($this->assignable($me))),
        ]);
    }

    public function store(Request $request)
    {
        $me = $request->attributes->get('membership');
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190',
            'role' => ['required', Rule::in($this->assignable($me))],
        ]);
        $user = User::where('email', strtolower($data['email']))->first();
        if ($user && ! $user->isRole('customer')) {
            return back()->withErrors(['email' => 'This email belongs to a staff or rider account and cannot join a business.']);
        }
        if ($user && BusinessMember::where('user_id', $user->id)->exists()) {
            return back()->withErrors(['email' => 'This person already belongs to a business account.']);
        }
        if (! $user) {
            $user = User::create(['name' => $data['name'], 'email' => strtolower($data['email']), 'password' => Str::password(32), 'role' => 'customer']);
            Password::sendResetLink(['email' => $user->email]); // Invite: they set their own password.
        }
        BusinessMember::create(['business_id' => $me->business_id, 'user_id' => $user->id, 'role' => $data['role']]);
        Audit::log('business.member_added', $me->business, ['user_id' => $user->id, 'role' => $data['role']]);

        return back()->with('success', "{$data['name']} has been added. New users receive an email to set their password.");
    }

    public function update(Request $request, BusinessMember $member)
    {
        $me = $request->attributes->get('membership');
        abort_unless($member->business_id === $me->business_id, 404);
        abort_if($member->role === 'owner' && $me->role !== 'owner', 403);
        $data = $request->validate(['role' => ['required', Rule::in($this->assignable($me))]]);
        if ($member->role === 'owner' && $data['role'] !== 'owner' && BusinessMember::where('business_id', $me->business_id)->where('role', 'owner')->count() < 2) {
            return back()->with('error', 'A business must keep at least one owner.');
        }
        Audit::log('business.member_role_changed', $me->business, ['user_id' => $member->user_id, 'from' => $member->role, 'to' => $data['role']]);
        $member->update($data);

        return back()->with('success', 'Role updated.');
    }

    public function destroy(Request $request, BusinessMember $member)
    {
        $me = $request->attributes->get('membership');
        abort_unless($member->business_id === $me->business_id, 404);
        abort_if($member->role === 'owner' && $me->role !== 'owner', 403);
        if ($member->user_id === $me->user_id) {
            return back()->with('error', 'You cannot remove yourself.');
        }
        Audit::log('business.member_removed', $me->business, ['user_id' => $member->user_id]);
        $member->delete();

        return back()->with('success', 'Team member removed.');
    }
}
