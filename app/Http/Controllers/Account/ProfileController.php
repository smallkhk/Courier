<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('account.profile', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', 'max:190', Rule::unique('users')->ignore($user->id)],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9 ()-]{7,20}$/'],
            'notify_email' => 'nullable|boolean',
            'notify_sms' => 'nullable|boolean',
        ]);
        $emailChanged = strtolower($data['email']) !== $user->email;
        $user->fill(['name' => $data['name'], 'email' => strtolower($data['email']), 'phone' => $data['phone']]);
        $user->notification_preferences = ['email' => $request->boolean('notify_email'), 'sms' => $request->boolean('notify_sms')];
        $user->sms_consent_at = $request->boolean('notify_sms') ? ($user->sms_consent_at ?? now()) : null;
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        $user->save();
        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return back()->with('success', $emailChanged ? 'Profile saved. Please verify your new email address.' : 'Profile saved.');
    }

    public function password(Request $request)
    {
        $request->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);
        $request->user()->forceFill(['password' => Hash::make($request->input('password'))])->save();
        Audit::log('auth.password_changed', $request->user());

        return back()->with('success', 'Password changed.');
    }

    public function notifications(Request $request)
    {
        return view('account.notifications', [
            'logs' => NotificationLog::where('user_id', $request->user()->id)->with('shipment:id,tracking_number')->latest()->paginate(25),
        ]);
    }
}
