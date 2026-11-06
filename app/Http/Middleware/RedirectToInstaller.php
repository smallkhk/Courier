<?php

namespace App\Http\Middleware;

use App\Support\Installer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Before the first install, send visitors to the setup page instead of a database error. */
class RedirectToInstaller
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('install', 'up', 'health') && Installer::enabled()) {
            return redirect('/install');
        }

        return $next($request);
    }
}
