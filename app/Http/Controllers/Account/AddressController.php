<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Services\ShipmentDetails;
use App\Support\Countries;
use App\Support\Phone;
use App\Support\Regions;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AddressController extends Controller
{
    /** Validate the address-book form (fields prefixed "a_" by the address picker) and map to columns. */
    public static function attributesFrom(Request $request): array
    {
        $d = $request->validate(ShipmentDetails::addressRules('a') + [
            'label' => 'required|string|max:60',
            'contact_name' => 'required|string|max:120',
            'phone' => 'required|string|max:32',
            'email' => 'nullable|email|max:190',
            'landmark' => 'nullable|string|max:150',
        ]);
        $country = strtoupper($d['a_country']);
        $postal = strtoupper(trim((string) ($d['a_postal_code'] ?? ''))) ?: null;
        $errors = [];
        if (! $phone = Phone::toE164($d['phone'], $country)) {
            $errors['phone'] = 'Enter a valid phone number for '.Countries::name($country).' (or include the country code).';
        }
        if (Regions::postalRequired($country) && ! $postal) {
            $errors['a_postal_code'] = 'A ZIP / postal code is required.';
        } elseif ($postal && ! Regions::postalValid($country, $postal)) {
            $errors['a_postal_code'] = 'That ZIP / postal code doesn\'t look right.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'label' => $d['label'], 'contact_name' => $d['contact_name'], 'phone' => $phone, 'email' => $d['email'] ?? null,
            'line1' => $d['a_address'], 'line2' => $d['a_address2'] ?? null, 'city' => $d['a_city'],
            'region' => Regions::normalize($country, $d['a_region'] ?? null), 'postal_code' => $postal, 'country_code' => $country,
            'lat' => $d['a_lat'] ?? null, 'lng' => $d['a_lng'] ?? null, 'place_id' => $d['a_place_id'] ?? null,
            'landmark' => $d['landmark'] ?? null,
        ];
    }

    public function index(Request $request)
    {
        return view('account.addresses', [
            'addresses' => $request->user()->addresses()->orderBy('label')->get(),
            'action' => route('account.addresses.store'),
            'portal' => 'account',
        ]);
    }

    public function store(Request $request)
    {
        $a = new Address(self::attributesFrom($request));
        $a->user_id = $request->user()->id;
        $a->save();

        return back()->with('success', 'Address saved.');
    }

    public function update(Request $request, Address $address)
    {
        abort_unless($address->user_id === $request->user()->id, 404);
        $address->update(self::attributesFrom($request));

        return back()->with('success', 'Address updated.');
    }

    public function destroy(Request $request, Address $address)
    {
        abort_unless($address->user_id === $request->user()->id, 404);
        $address->delete();

        return back()->with('success', 'Address removed.');
    }
}
