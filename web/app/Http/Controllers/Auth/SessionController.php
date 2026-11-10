<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sign in and sign out (PB-07). A "session" here is the logged-in state Laravel keeps in the
 * sessions table, identified by the browser's session cookie.
 */
class SessionController extends Controller
{
    /**
     * Show the sign-in form.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Sign the user in and send them to their portal.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        // Checks the password and the rate limit; throws a validation error if either fails.
        $request->authenticate();

        // A fresh session id on sign-in prevents session fixation.
        $request->session()->regenerate();

        // intended(): if 'auth' sent them here from a page they tried to open, go back there;
        // otherwise go to their own portal.
        return redirect()->intended(route($request->user()->portalRoute()));
    }

    /**
     * Sign out: forget the user, destroy the session and issue a new CSRF token.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
