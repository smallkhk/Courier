<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Support\Audit;
use App\Support\ConfigCheck;
use App\Support\Settings;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit()
    {
        return view('admin.settings', [
            'values' => Settings::all(),
            'integrations' => [
                'Payments' => config('courier.payments.provider').(config('courier.payments.provider') === 'paystack' ? (config('courier.payments.paystack.secret_key') ? ' (key set)' : ' (KEY MISSING)') : ' (sandbox — development only)'),
                'Email' => config('mail.default').(config('mail.default') === 'log' ? ' (written to log, not sent)' : ''),
                'SMS' => config('courier.sms.driver').(config('courier.sms.driver') === 'log' ? ' (written to log, not sent)' : ''),
                'Map tiles' => parse_url(config('courier.maps.tile_url'), PHP_URL_HOST),
                'Scheduler (cron)' => cache('scheduler:last_run') ? 'last ran '.cache('scheduler:last_run')->diffForHumans() : 'NOT RUNNING — set up the cPanel cron job',
                'Environment' => app()->environment().(config('app.debug') ? ' (debug ON)' : ''),
            ],
            'problems' => ConfigCheck::problems(),
        ]);
    }

    public function update(Request $request)
    {
        $rules = [];
        foreach (Settings::EDITABLE as $key => [$type]) {
            $rules[$key] = match ($type) {
                'bool' => 'nullable|boolean',
                'int' => 'required|integer|min:0|max:100000',
                'email' => 'nullable|email',
                'list' => 'nullable|string|max:200',
                default => 'nullable|string|max:250',
            };
        }
        $rules['tracking_prefix'] = 'required|alpha|min:2|max:4';
        $rules['default_currency'] = 'required|alpha|size:3';
        $rules['timezone'] = 'required|timezone';
        $data = $request->validate($rules);

        $changed = [];
        foreach (Settings::EDITABLE as $key => [$type]) {
            $val = match ($type) {
                'bool' => $request->boolean($key),
                'int' => (int) $data[$key],
                'list' => array_values(array_filter(array_map(fn ($c) => strtoupper(trim($c)), explode(',', (string) ($data[$key] ?? ''))))),
                default => $data[$key] ?? '',
            };
            if ($key === 'tracking_prefix' || $key === 'default_currency') {
                $val = strtoupper($val);
            }
            if (Settings::get($key) !== $val) {
                $changed[$key] = $val;
                Settings::set($key, $val);
            }
        }
        Audit::log('admin.settings_updated', null, ['changed' => $changed]);

        return back()->with('success', $changed ? 'Settings saved.' : 'No changes.');
    }

    public function workflow()
    {
        return view('admin.workflow', ['statuses' => ShipmentStatus::cases(), 'transitions' => ShipmentStatus::transitions()]);
    }
}
