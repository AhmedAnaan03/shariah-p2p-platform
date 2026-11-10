<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Validates the change-password form for a signed-in user (PB-08).
 */
class ChangePasswordRequest extends FormRequest
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
            // current_password checks the value against the signed-in user's stored hash.
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Enter your current password.',
            'current_password.string' => 'Enter your current password.',
            'current_password.current_password' => 'Your current password is incorrect.',
            'password.required' => 'Enter a new password.',
            'password.string' => 'Enter a new password.',
            'password.min' => 'Your password must be at least 8 characters.',
            'password.confirmed' => 'The two new passwords do not match.',
            'password.different' => 'Your new password must be different from your current one.',
        ];
    }
}
