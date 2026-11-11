<?php

namespace App\Http\Requests\Auth;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validates the registration form (PB-06). Runs before the controller; if any rule fails,
 * Laravel redirects back with the errors and the controller never runs.
 */
class RegisterRequest extends FormRequest
{
    /**
     * The roles a visitor may pick. 'admin' is deliberately missing: administrators are
     * never created through the public form.
     */
    public const SELF_REGISTER_ROLES = [Role::BORROWER, Role::INVESTOR];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Store emails in one form so "Aisha@Example.com" and "aisha@example.com" are the same
     * account. (TrimStrings middleware has already removed surrounding spaces.)
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower($this->input('email'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'role' => ['required', 'string', Rule::in(self::SELF_REGISTER_ROLES)],
        ];
    }

    /**
     * Plain-language messages for every rule that can fail. The password-strength messages
     * come from lang/en/validation.php (password.mixed etc.).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter your full name.',
            'name.string' => 'Enter your full name.',
            'name.max' => 'Your name must be 255 characters or fewer.',
            'email.required' => 'Enter your email address.',
            'email.string' => 'Enter a valid email address, like name@example.com.',
            'email.email' => 'Enter a valid email address, like name@example.com.',
            'email.max' => 'Your email address must be 255 characters or fewer.',
            'email.unique' => 'An account with this email address already exists. Sign in instead.',
            'password.required' => 'Enter a password.',
            'password.string' => 'Enter a password.',
            'password.min' => 'Your password must be at least 8 characters.',
            'password.confirmed' => 'The two passwords do not match.',
            'role.required' => 'Choose whether you are registering as a borrower or an investor.',
            'role.string' => 'Choose whether you are registering as a borrower or an investor.',
            'role.in' => 'Choose whether you are registering as a borrower or an investor.',
        ];
    }
}
