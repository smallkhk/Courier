<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SpamGuard;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    public function show()
    {
        return view('auth.register');
    }

    public function store(Request $request)
    {
        SpamGuard::check($request);
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email:rfc|max:190|unique:users,email',
            'phone' => ['required', 'string', 'regex:/^\+?[0-9 ()-]{7,20}$/'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'accept_terms' => 'accepted',
            'sms_consent' => 'nullable|boolean',
        ], ['accept_terms.accepted' => 'You must accept the terms and privacy policy.']);

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'phone' => $data['phone'],
            'password' => $data['password'],
            'role' => 'customer', // Public registration can never create staff or riders.
            'sms_consent_at' => $request->boolean('sms_consent') ? now() : null,
            'notification_preferences' => ['email' => true, 'sms' => $request->boolean('sms_consent')],
        ]);
        event(new Registered($user)); // sends the verification email
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('account.dashboard'))->with('success', 'Welcome! Please check your email to verify your address.');
    }
}
