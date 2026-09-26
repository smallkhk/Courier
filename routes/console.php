<?php

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
