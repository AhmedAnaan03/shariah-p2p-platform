<?php

namespace App\Http\Requests\Account;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the sidebar's theme toggle (PB-55): light, dark or system.
 */
class ThemePreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The 'auth' route middleware is the gate; it only changes the user's own setting.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'theme' => ['required', 'string', Rule::in(User::THEMES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $message = 'Choose light, dark or system.';

        return ['theme.required' => $message, 'theme.string' => $message, 'theme.in' => $message];
    }
}
