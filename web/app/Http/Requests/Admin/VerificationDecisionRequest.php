<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an administrator's approve / reject decision (PB-11). The route is already behind
 * role:admin, so authorize() has nothing more to check.
 */
class VerificationDecisionRequest extends FormRequest
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
            'decision' => ['required', 'string', Rule::in(['approve', 'reject'])],
            // A reason only for a rejection, where it is required. 255 = the column size
            // (users.status_reason and kyc_documents.rejection_reason).
            'reason' => ['exclude_unless:decision,reject', 'required', 'string', 'min:10', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.required' => 'Choose whether to approve or reject this account.',
            'decision.string' => 'Choose whether to approve or reject this account.',
            'decision.in' => 'Choose whether to approve or reject this account.',
            'reason.required' => 'Give a reason for the rejection. The user will see it.',
            'reason.string' => 'Give a reason for the rejection. The user will see it.',
            'reason.min' => 'Write at least 10 characters so the user knows what to fix.',
            'reason.max' => 'The reason must be 255 characters or fewer.',
        ];
    }
}
