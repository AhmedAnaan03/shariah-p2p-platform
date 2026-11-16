<?php

namespace App\Http\Requests\Investor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the top bar's Shariah-only switch (PB-51). Only the investor route group reaches
 * this, and it only ever changes the signed-in investor's own profile.
 */
class ShariahPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The 'role:investor' route middleware is the gate.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // 'boolean' accepts true/false, 1/0 and "1"/"0" and nothing else.
            'shariah_only' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'shariah_only.required' => 'Choose whether Shariah-only mode is on or off.',
            'shariah_only.boolean' => 'Shariah-only mode can only be on or off.',
        ];
    }
}
