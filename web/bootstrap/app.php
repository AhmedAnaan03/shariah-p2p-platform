<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // Lets routes say ->middleware('role:borrower').
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        // 'auth' sends guests to the sign-in page; 'guest' sends signed-in users to their own
        // portal (e.g. a signed-in investor opening /login lands on /investor).
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => route($request->user()->portalRoute()));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A signed-out browser sending a form to a signed-in route (the session expired, then
        // Sign out, a pledge, a top-up): Laravel would remember the page the form was sent from
        // as the "intended" URL, and whoever signs in next in this browser would be sent there.
        // A form isn't a page to return to, so: Sign out lands on the home page (they are signed
        // out already), anything else on the sign-in page, and nothing is remembered. A page
        // someone tried to open (GET) is still remembered; IntendedUrl checks it at sign-in.
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->expectsJson() || $request->isMethod('GET') || $request->isMethod('HEAD')) {
                return null; // Laravel's default
            }

            return $request->routeIs('logout') ? redirect()->route('home') : redirect()->route('login');
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
