<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use App\Support\Installer;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * First-launch setup without a terminal: creates the database tables, the essential
 * data (message templates, policy drafts) and the first administrator. It works only
 * while no administrator exists AND INSTALL_TOKEN is set in .env, and locks itself
 * afterwards. Demo data is never loaded.
 */
class InstallController extends Controller
{
    public function show()
    {
        abort_unless(Installer::enabled(), 404);

        return view('install.index', ['requirements' => Installer::requirements()]);
    }

    public function store(Request $request)
    {
        abort_unless(Installer::enabled(), 404);
        $key = 'install:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['install_token' => 'Too many attempts. Try again in '.ceil(RateLimiter::availableIn($key) / 60).' minutes.']);
        }
        RateLimiter::hit($key, 600);

        $data = $request->validate([
            'install_token' => 'required|string',
            'business_name' => 'required|string|max:120',
            'support_email' => 'required|email:rfc|max:190',
            'name' => 'required|string|max:120',
            'email' => 'required|email:rfc|max:190',
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
        ]);
        if (! hash_equals((string) config('courier.install_token'), $data['install_token'])) {
            throw ValidationException::withMessages(['install_token' => 'The setup code does not match INSTALL_TOKEN in your .env file.']);
        }
        if (collect(Installer::requirements())->contains(fn ($r) => ! $r[1])) {
            return back()->withInput($request->except('password', 'password_confirmation', 'install_token'))->with('error', 'Fix the items marked in red first.');
        }

        @set_time_limit(300);
        try {
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\EssentialSeeder', '--force' => true]);
        } catch (Throwable $e) {
            report($e);

            return back()->withInput($request->except('password', 'password_confirmation', 'install_token'))
                ->with('error', 'Creating the database tables failed: '.$e->getMessage());
        }

        $admin = User::create([
            'name' => $data['name'], 'email' => strtolower($data['email']), 'password' => $data['password'],
            'role' => 'admin', 'status' => 'active',
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        Settings::set('business_name', $data['business_name']);
        Settings::set('support_email', $data['support_email']);
        file_put_contents(Installer::lockFile(), json_encode(['installed_at' => now()->toIso8601String(), 'version' => config('courier.version')]));
        Audit::log('system.installed', $admin, ['version' => config('courier.version')], $admin->id);
        RateLimiter::clear($key);

        return redirect()->route('login')->with('success', 'ExpressShipping is installed. Sign in with the administrator account you just created, then finish the launch checklist in System settings.');
    }
}
