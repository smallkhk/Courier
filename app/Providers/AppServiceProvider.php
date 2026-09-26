<?php

namespace App\Providers;

use App\Support\ConfigCheck;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
            // Make configuration errors loud at startup (visible in storage/logs).
            foreach (ConfigCheck::problems() as [$level, $msg]) {
                $level === 'error' ? Log::critical('CONFIG: '.$msg) : Log::warning('CONFIG: '.$msg);
            }
        }

        Paginator::defaultView('pagination::tailwind');

        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by(strtolower((string) $r->input('email')).'|'.$r->ip()),
            Limit::perMinute(20)->by($r->ip()),
        ]);
        RateLimiter::for('password-reset', fn (Request $r) => Limit::perMinutes(10, 5)->by($r->ip()));
        RateLimiter::for('tracking', fn (Request $r) => [Limit::perMinute(20)->by($r->ip()), Limit::perHour(300)->by($r->ip())]);
        RateLimiter::for('tracking-verify', fn (Request $r) => Limit::perMinutes(10, 5)->by($r->ip().'|'.$r->route('tracking')));
        RateLimiter::for('quote', fn (Request $r) => Limit::perMinute(20)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('contact', fn (Request $r) => Limit::perMinutes(10, 5)->by($r->ip()));
        RateLimiter::for('booking', fn (Request $r) => Limit::perMinute(10)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('rider-location', fn (Request $r) => Limit::perMinute(12)->by((string) $r->user()?->id));
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
    }
}
