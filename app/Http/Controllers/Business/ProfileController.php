<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('business.profile', ['business' => $request->attributes->get('membership')->business]);
    }

    public function update(Request $request)
    {
        $business = $request->attributes->get('membership')->business;
        // Status, payment terms and pricing can only be changed by our administrators.
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'registration_number' => 'nullable|string|max:64',
            'email' => 'required|email|max:190',
            'phone' => 'required|string|max:32',
            'billing_email' => 'nullable|email|max:190',
            'billing_address' => 'required|string|max:250',
        ]);
        $business->update($data);
        Audit::log('business.profile_updated', $business);

        return back()->with('success', 'Company profile saved.');
    }
}
