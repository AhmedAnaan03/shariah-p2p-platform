<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class ForgotPasswordController extends Controller
{
    /**
     * The same answer whether or not the email has an account, so the form can't be used to
     * find out who is registered.
     */
    public const SENT_MESSAGE = 'If an account exists for that email address, we have sent it a password reset link.';

    /**
     * Show the "forgot password" form.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    /**
     * Email a reset link if the account exists.
     */
    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        // Laravel's password broker: stores a hashed token in password_reset_tokens and sends
        // the ResetPassword notification, whose link points at the 'password.reset' route.
        // In development MAIL_MAILER=log, so the email is written to storage/logs/laravel.log.
        // The returned status (sent / no such user / throttled) is deliberately not shown.
        Password::sendResetLink($request->only('email'));

        return back()->with('status', self::SENT_MESSAGE);
    }
}
