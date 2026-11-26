<?php

namespace App\Http\Requests\Admin;

use App\Models\BusinessReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An administrator's decision on a Mudarabah business report (PB-29). Rejecting needs a reason
 * the business is shown. Approving a loss needs its cause: a genuine business loss, or
 * negligence or misconduct, which needs a reason too (it is recorded and audit-logged). Whether
 * the report can still be decided, and its figures, are checked by BusinessReportService.
 */
class BusinessReportDecisionRequest extends FormRequest
{
    public const APPROVE = 'approve';

    public const REJECT = 'reject';

    public function authorize(): bool
    {
        return true; // role:admin on the route group
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var BusinessReport $report */
        $report = $this->route('report');
        $needsCause = $this->input('decision') === self::APPROVE && $report->isLoss();
        $needsReason = $this->input('decision') === self::REJECT
            || ($needsCause && $this->input('loss_cause') === BusinessReport::CAUSE_NEGLIGENCE);

        return [
            'decision' => ['required', 'string', Rule::in([self::APPROVE, self::REJECT])],
            'loss_cause' => $needsCause
                ? ['required', 'string', Rule::in([BusinessReport::CAUSE_BUSINESS, BusinessReport::CAUSE_NEGLIGENCE])]
                : ['exclude'],
            'reason' => $needsReason ? ['required', 'string', 'min:10', 'max:1000'] : ['nullable', 'string', 'max:1000'],
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
            'loss_cause.required' => 'Choose whether this was a genuine business loss or caused by negligence or misconduct.',
            'loss_cause.string' => 'Choose whether this was a genuine business loss or caused by negligence or misconduct.',
            'loss_cause.in' => 'Choose a cause from the list.',
            'reason.required' => 'Give a reason. The business will see it, and it is recorded in the audit log.',
            'reason.string' => 'Give a reason. The business will see it, and it is recorded in the audit log.',
            'reason.min' => 'Write at least 10 characters so the business knows why.',
            'reason.max' => 'The reason must be 1,000 characters or fewer.',
        ];
    }
}
