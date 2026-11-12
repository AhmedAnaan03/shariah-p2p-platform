<?php

namespace App\Http\Requests\Investor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The investor ticks one box to accept the risk acknowledgement (PB-10).
 */
class RiskAcknowledgementRequest extends FormRequest
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
        // 'accepted' passes only for yes / on / 1 / true: an unticked or missing box fails.
        return [
            'accepted' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'accepted.accepted' => 'Tick the box to confirm you have read and understood the risks.',
        ];
    }
}
