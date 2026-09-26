<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /** Uptime-monitor endpoint. Reveals no configuration details. */
    public function __invoke()
    {
        $checks = ['app' => 'ok'];
        try {
            DB::select('select 1');
            $checks['database'] = 'ok';
        } catch (\Throwable) {
            $checks['database'] = 'fail';
        }
        $lastRun = cache('scheduler:last_run');
        $checks['scheduler'] = $lastRun && now()->diffInMinutes($lastRun, true) < 5 ? 'ok' : 'stale';
        $ok = $checks['database'] === 'ok';

        return response()->json(['status' => $ok ? 'ok' : 'degraded', 'checks' => $checks, 'time' => now()->toIso8601String()], $ok ? 200 : 503);
    }
}
