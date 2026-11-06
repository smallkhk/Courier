<?php

use App\Services\Updates\UpdateService;
use Illuminate\Support\Facades\Schedule;

/*
| On cPanel, a single cron job runs the scheduler every minute:
|   * * * * * cd /home/USER/courier && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
| withoutOverlapping() uses the cache lock so concurrent cron runs never double-process.
*/

Schedule::command('notifications:send')->everyMinute()->withoutOverlapping(10);
Schedule::command('payments:reconcile')->everyTenMinutes()->withoutOverlapping(15);
Schedule::command('courier:prune')->dailyAt('02:30');
// Generic Laravel queue (not required by core flows) processed without a daemon:
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping(2);
// Heartbeat so /health can report whether the cPanel cron is running.
Schedule::call(fn () => cache()->put('scheduler:last_run', now(), 3600))->everyMinute()->name('scheduler-heartbeat');
// Completes a one-click update if the admin's browser closed before it finished.
Schedule::command('courier:finish-update')->everyMinute()->withoutOverlapping(10)->evenInMaintenanceMode();
// Look for a newer release so admins see "Update available" on their dashboard.
Schedule::call(function () {
    try {
        app(UpdateService::class)->latest(fresh: true);
    } catch (Throwable) {
        // Offline or rate-limited: try again next hour.
    }
})->hourly()->name('check-for-updates');
