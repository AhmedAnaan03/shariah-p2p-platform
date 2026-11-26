<?php

namespace App\Http\Requests\Repayment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Paying the next instalment (PB-35). schedule_id is the instalment the borrower saw as next:
 * RepaymentService pays it only if it still is, which is what makes a double click harmless.
 * Only verified users move money ('move-money'); ownership is checked by the controller.
 */
class PayInstalmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('move-money');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'schedule_id' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'schedule_id.required' => 'Choose the instalment to pay.',
            'schedule_id.integer' => 'Choose the instalment to pay.',
            'schedule_id.min' => 'Choose the instalment to pay.',
        ];
    }
}
