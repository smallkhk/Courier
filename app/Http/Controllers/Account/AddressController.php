<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\ServiceZone;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public static function rules(): array
    {
        return [
            'label' => 'required|string|max:60',
            'contact_name' => 'required|string|max:120',
            'phone' => ['required', 'string', 'regex:/^\+?[0-9 ()-]{7,20}$/'],
            'email' => 'nullable|email|max:190',
            'line1' => 'required|string|max:200',
            'line2' => 'nullable|string|max:200',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'service_zone_id' => 'required|exists:service_zones,id',
            'landmark' => 'nullable|string|max:150',
        ];
    }

    public function index(Request $request)
    {
        return view('account.addresses', [
            'addresses' => $request->user()->addresses()->with('zone')->orderBy('label')->get(),
            'zones' => ServiceZone::where('active', true)->orderBy('name')->get(),
            'action' => route('account.addresses.store'),
            'portal' => 'account',
        ]);
    }

    public function store(Request $request)
    {
        $a = new Address($request->validate(self::rules()));
        $a->user_id = $request->user()->id;
        $a->save();

        return back()->with('success', 'Address saved.');
    }

    public function update(Request $request, Address $address)
    {
        abort_unless($address->user_id === $request->user()->id, 404);
        $address->update($request->validate(self::rules()));

        return back()->with('success', 'Address updated.');
    }

    public function destroy(Request $request, Address $address)
    {
        abort_unless($address->user_id === $request->user()->id, 404);
        $address->delete();

        return back()->with('success', 'Address removed.');
    }
}
