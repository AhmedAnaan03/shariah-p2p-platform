<?php

namespace App\Http\Requests\Onboarding;

use App\Models\CampaignFinancials;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the borrower's financial profile, the onboarding wizard's finance step (PB-58; the
 * fields and messages were on the financing request form in PB-14).
 *
 * Unlike the other steps it stays open after verification: a verified borrower whose finances
 * change updates them here, which sends the account back to the administrator
 * (OnboardingController::saveFinances). Only a suspended account can't change them.
 */
class FinancialProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->status !== 'suspended';
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Your account is suspended, so your financial profile can\'t be changed.');
    }

    /**
     * Money arrives as typed digits from the MoneyInput (no separators), but strip any commas
     * or spaces someone types elsewhere.
     */
    protected function prepareForValidation(): void
    {
        foreach (['monthly_income', 'monthly_debt_repayments'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => preg_replace('/[\s,]+/', '', $this->input($field))]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $count = ['required', 'integer', 'min:0', 'max:'.CampaignFinancials::MAX_COUNT];

        return [
            'monthly_income' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:'.CampaignFinancials::MAX_MONTHLY_INCOME],
            'monthly_debt_repayments' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:'.CampaignFinancials::MAX_MONTHLY_INCOME],
            'employment_years' => ['required', 'integer', 'min:0', 'max:'.CampaignFinancials::MAX_EMPLOYMENT_YEARS],
            'housing' => ['required', 'string', Rule::in(array_keys(CampaignFinancials::HOUSING))],
            // Self-declared credit history, checked against the MMA credit report.
            'open_credit_lines' => $count,
            'late_payments_2y' => $count,
            'public_records' => $count,
        ];
    }

    /**
     * Every message says what is wrong and how to fix it.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $max = 'MVR '.number_format(CampaignFinancials::MAX_MONTHLY_INCOME);
        $count = 'Enter a whole number from 0 to '.CampaignFinancials::MAX_COUNT.'. Enter 0 if none.';
        $employment = 'Choose how long you have been in your job or running the business.';

        return [
            'monthly_income.required' => 'Enter your monthly income before tax.',
            'monthly_income.numeric' => 'Enter your monthly income as a number, like 40000.',
            'monthly_income.decimal' => 'Enter your monthly income with at most 2 decimal places.',
            'monthly_income.gt' => 'Your monthly income must be more than 0.',
            'monthly_income.max' => "Check your monthly income: it must be {$max} or less.",
            'monthly_debt_repayments.required' => 'Enter what you repay each month on existing loans and cards. Enter 0 if nothing.',
            'monthly_debt_repayments.numeric' => 'Enter the monthly repayments as a number, like 6000.',
            'monthly_debt_repayments.decimal' => 'Enter the monthly repayments with at most 2 decimal places.',
            'monthly_debt_repayments.min' => 'Monthly repayments can\'t be negative. Enter 0 if nothing.',
            'monthly_debt_repayments.max' => "Check your monthly repayments: they must be {$max} or less.",
            'employment_years.required' => $employment,
            'employment_years.integer' => $employment,
            'employment_years.min' => $employment,
            'employment_years.max' => $employment,
            'housing.required' => 'Choose your housing situation.',
            'housing.string' => 'Choose your housing situation.',
            'housing.in' => 'Choose your housing situation from the list.',
            'open_credit_lines.required' => 'Enter how many loans, financing facilities and credit cards you have open. Enter 0 if none.',
            'open_credit_lines.integer' => $count,
            'open_credit_lines.min' => $count,
            'open_credit_lines.max' => $count,
            'late_payments_2y.required' => 'Enter how many times a repayment was 30 or more days late in the past 2 years. Enter 0 if never.',
            'late_payments_2y.integer' => $count,
            'late_payments_2y.min' => $count,
            'late_payments_2y.max' => $count,
            'public_records.required' => 'Enter how many court judgments, bankruptcies or written-off debts you have had. Enter 0 if none.',
            'public_records.integer' => $count,
            'public_records.min' => $count,
            'public_records.max' => $count,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function financialData(): array
    {
        return $this->validated();
    }
}
