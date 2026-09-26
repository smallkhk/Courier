<?php

use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureBusinessMember;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // JSON API. Uses the web middleware group so browser clients authenticate with the
            // same secure session cookie and CSRF protection; webhooks are exempted below.
            Route::middleware('web')->prefix('api')->name('api.')->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Only trust proxies you actually run behind (e.g. Cloudflare), otherwise clients
        // could spoof X-Forwarded-For and evade IP rate limits.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
        $middleware->web(append: [
            SecurityHeaders::class,
            EnsureActiveAccount::class,
        ]);
        $middleware->validateCsrfTokens(except: ['api/webhooks/*']);
        $middleware->alias([
            'role' => EnsureRole::class,
            'business' => EnsureBusinessMember::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('portal'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*') || $request->expectsJson());
    })->create();
