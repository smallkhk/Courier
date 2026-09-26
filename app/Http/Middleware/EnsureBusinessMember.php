<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ->middleware('business')                 any member of an approved business
 * ->middleware('business:manage_team')     member whose business role grants the permission
 * The resolved membership is shared as $request->attributes->get('membership').
 */
class EnsureBusinessMember
{
    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $membership = $request->user()?->primaryMembership();
        if (! $membership) {
            return redirect()->route('business.register');
        }
        if (! $membership->business->isApproved()) {
            if ($request->routeIs('business.dashboard')) {
                $request->attributes->set('membership', $membership);

                return $next($request);
            }
            abort(403, 'Your business account is not active yet ('.$membership->business->status.').');
        }
        if ($permission && ! $membership->can($permission)) {
            abort(403, 'Your team role does not allow this action.');
        }
        $request->attributes->set('membership', $membership);

        return $next($request);
    }
}
