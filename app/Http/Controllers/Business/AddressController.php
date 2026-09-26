<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Account\AddressController as Base;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\ServiceZone;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        $m = $request->attributes->get('membership');

        return view('account.addresses', [
            'addresses' => Address::where('business_id', $m->business_id)->with('zone')->orderBy('label')->get(),
            'zones' => ServiceZone::where('active', true)->orderBy('name')->get(),
            'action' => route('business.addresses.store'),
            'portal' => 'business',
        ]);
    }

    public function store(Request $request)
    {
        $a = new Address($request->validate(Base::rules()));
        $a->business_id = $request->attributes->get('membership')->business_id;
        $a->save();

        return back()->with('success', 'Address saved.');
    }

    public function destroy(Request $request, Address $address)
    {
        abort_unless($address->business_id === $request->attributes->get('membership')->business_id, 404);
        $address->delete();

        return back()->with('success', 'Address removed.');
    }
}
