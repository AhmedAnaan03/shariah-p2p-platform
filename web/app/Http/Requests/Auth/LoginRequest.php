<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Validates the sign-in form and checks the credentials, with rate limiting (PB-07).
 */
class LoginRequest extends FormRequest
{
    /**
     * Failed attempts allowed per email + IP address before sign-in is locked.
     */
    public const MAX_ATTEMPTS = 5;

    /**
     * How long the lock lasts, in seconds.
     */
    public const DECAY_SECONDS = 60;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Enter your email address.',
            'email.string' => 'Enter a valid email address, like name@example.com.',
            'email.email' => 'Enter a valid email address, like name@example.com.',
            'password.required' => 'Enter your password.',
            'password.string' => 'Enter your password.',
        ];
    }

    /**
     * Sign the user in, or throw a validation error on the email field.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            // Count the failure. After MAX_ATTEMPTS the key is locked for DECAY_SECONDS.
            RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);

            // One message for "no such email" and "wrong password", so the form can't be used
            // to find out which emails have accounts.
            throw ValidationException::withMessages([
                'email' => 'These details do not match our records.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Too many sign-in attempts. Try again in {$seconds} seconds.",
        ]);
    }

    /**
     * Limit per account *and* per address: one attacker can't lock out every user, and
     * guessing one account's password from many places is still limited per address.
     */
    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
