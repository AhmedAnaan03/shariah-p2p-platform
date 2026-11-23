<?php

namespace App\Http\Requests\Account;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the sidebar's theme switch (PB-55, PB-57): the chosen theme, light or dark, and the
 * device's own setting at that moment, so a later change of the device setting can win.
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
            'system' => ['required', 'string', Rule::in(User::THEMES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $message = 'Choose light or dark.';
        $system = 'Send the device\'s own setting, light or dark.';

        return [
            'theme.required' => $message, 'theme.string' => $message, 'theme.in' => $message,
            'system.required' => $system, 'system.string' => $system, 'system.in' => $system,
        ];
    }
}
