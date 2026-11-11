<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ResetPasswordController extends Controller
{
    /**
     * Show the "choose a new password" form. The token and email come from the emailed link.
     */
    public function create(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->string('email')->value(),
        ]);
    }

    /**
     * Check the token and save the new password.
     */
    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        // The broker checks that the token matches this email and is younger than
        // auth.passwords.users.expire (60 minutes), runs the callback, then deletes the token
        // so the link can't be used twice.
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password, // hashed by the model's 'hashed' cast
                    // A new remember token signs out any "remember me" sessions.
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            // Wrong, used or expired token, or an email with no account: one message for all.
            throw ValidationException::withMessages([
                'email' => 'This reset link is invalid or has expired. Request a new one.',
            ]);
        }

        return redirect()
            ->route('login')
            ->with('status', 'Your password has been reset. Sign in with your new password.');
    }
}
