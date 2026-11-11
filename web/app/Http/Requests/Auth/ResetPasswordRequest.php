<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Validates the "choose a new password" form reached from the emailed link (PB-08).
 */
class ResetPasswordRequest extends FormRequest
{
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
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.required' => 'This reset link is incomplete. Request a new one.',
            'token.string' => 'This reset link is incomplete. Request a new one.',
            'email.required' => 'Enter your email address.',
            'email.string' => 'Enter a valid email address, like name@example.com.',
            'email.email' => 'Enter a valid email address, like name@example.com.',
            'password.required' => 'Enter a new password.',
            'password.string' => 'Enter a new password.',
            'password.min' => 'Your password must be at least 8 characters.',
            'password.confirmed' => 'The two passwords do not match.',
        ];
    }
}
