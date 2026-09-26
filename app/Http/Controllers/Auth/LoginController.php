<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required|string']);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            Audit::log('auth.login_failed', null, ['email' => strtolower($credentials['email'])], null);
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }
        $user = Auth::user();
        if (! $user->isActive()) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'This account is suspended. Please contact support.']);
        }
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        if ($user->isStaff()) {
            Audit::log('auth.staff_login', $user);
        }

        return redirect()->intended(route('portal'));
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'You have been signed out.');
    }

    /** Send each role to its home portal. */
    public function portal(Request $request)
    {
        $user = $request->user();

        return match (true) {
            $user->isStaff() => redirect()->route('ops.dashboard'),
            $user->isRole('rider') => redirect()->route('rider.dashboard'),
            (bool) $user->primaryMembership() => redirect()->route('business.dashboard'),
            default => redirect()->route('account.dashboard'),
        };
    }
}
