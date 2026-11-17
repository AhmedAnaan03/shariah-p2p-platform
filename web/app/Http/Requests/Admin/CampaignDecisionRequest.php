<?php

namespace App\Http\Requests\Admin;

use App\Services\CampaignReviewService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an administrator's decision on a campaign (PB-15). Rejecting and suspending need a
 * reason, which the borrower is shown. The route is behind role:admin, so authorize() has
 * nothing more to check. Whether the decision fits the campaign's status is checked by
 * CampaignReviewService, under a row lock.
 */
class CampaignDecisionRequest extends FormRequest
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
            'decision' => ['required', 'string', Rule::in([
                CampaignReviewService::APPROVE, CampaignReviewService::REJECT, CampaignReviewService::SUSPEND,
            ])],
            // 255 = campaigns.status_reason and kyc_documents.rejection_reason.
            'reason' => ['exclude_if:decision,'.CampaignReviewService::APPROVE, 'required', 'string', 'min:10', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.required' => 'Choose a decision.',
            'decision.string' => 'Choose a decision.',
            'decision.in' => 'Choose a decision from the list.',
            'reason.required' => 'Give a reason. The borrower will see it.',
            'reason.string' => 'Give a reason. The borrower will see it.',
            'reason.min' => 'Write at least 10 characters so the borrower knows why.',
            'reason.max' => 'The reason must be 255 characters or fewer.',
        ];
    }
}
