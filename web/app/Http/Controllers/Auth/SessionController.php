<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\IntendedUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

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
     * Sign the user in and send them to the page they were trying to open, if it is theirs to
     * open, or else to their portal.
     */
    public function store(LoginRequest $request): SymfonyResponse
    {
        // Checks the password and the rate limit; throws a validation error if either fails.
        $request->authenticate();

        // A fresh session id on sign-in prevents session fixation.
        $request->session()->regenerate();

        // Not redirect()->intended(): the stored URL belongs to the browser, not to this user, so
        // it may be another user's or another role's page. IntendedUrl checks it first.
        return IntendedUrl::redirect($request, $request->user());
    }

    /**
     * Sign out: forget the user, destroy the session and issue a new CSRF token.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        // invalidate() empties the session, the intended URL included, so nothing from this
        // user's visit can steer the next sign-in in this browser.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
