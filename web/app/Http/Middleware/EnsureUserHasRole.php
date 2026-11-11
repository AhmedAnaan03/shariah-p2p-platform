<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets the request through only if the signed-in user has the role named in the route,
 * e.g. ->middleware('role:investor'). Always used after 'auth', so a user is present.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        // 403 Forbidden rather than a redirect: the user is signed in, they just aren't
        // allowed here, and a 403 is what the role-access tests check for.
        abort_unless($request->user()?->hasRole($role), 403);

        return $next($request);
    }
}
