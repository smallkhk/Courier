<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RegistrationController extends Controller
{
    public function show(Request $request)
    {
        if ($request->user()->primaryMembership()) {
            return redirect()->route('business.dashboard');
        }

        return view('business.register');
    }

    public function store(Request $request)
    {
        abort_if((bool) $request->user()->primaryMembership(), 422, 'You already belong to a business account.');
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'registration_number' => 'nullable|string|max:64',
            'email' => 'required|email|max:190',
            'phone' => ['required', 'string', 'regex:/^\+?[0-9 ()-]{7,20}$/'],
            'billing_email' => 'nullable|email|max:190',
            'billing_address' => 'required|string|max:250',
        ]);
        DB::transaction(function () use ($data, $request) {
            $b = Business::create($data + ['status' => 'pending']);
            BusinessMember::create(['business_id' => $b->id, 'user_id' => $request->user()->id, 'role' => 'owner']);
            Audit::log('business.registered', $b);
        });

        return redirect()->route('business.dashboard')->with('success', 'Thanks! Your business account is pending review. We will email you once it is approved.');
    }
}
