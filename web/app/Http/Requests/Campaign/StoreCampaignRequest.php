<?php

namespace App\Http\Requests\Campaign;

use App\Http\Requests\Kyc\StoreKycDocumentRequest;
use App\Models\Campaign;
use App\Models\CampaignFinancials;
use App\Models\KycDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Validates a new financing request (PB-13) with its financial information and optional MMA
 * credit report (PB-14). Every field the risk model uses is required, so a request with any of
 * them missing can't be submitted (PB-14 acceptance criterion).
 *
 * Contract terms are two-way, like the CHECK constraints on campaigns: each term is required
 * for its own contract and prohibited for the other two. So a Murabahah or Mudarabah request
 * that also sends a rate is refused here, before the database would refuse it.
 */
class StoreCampaignRequest extends FormRequest
{
    /**
     * Only a verified borrower (CampaignPolicy::create); anyone else gets 403 with the reason.
     */
    public function authorize(): bool
    {
        Gate::authorize('create', Campaign::class);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $extensions = implode(',', KycDocument::ALLOWED_EXTENSIONS);

        return [
            // The request (PB-13)
            'title' => ['required', 'string', 'min:5', 'max:150'],
            'purpose' => ['required', 'string', Rule::in(array_keys(Campaign::PURPOSES))],
            'description' => ['required', 'string', 'min:30', 'max:5000'],
            'business_background' => ['nullable', 'string', 'max:5000'],
            'amount_requested' => ['required', 'numeric', 'decimal:0,2', 'min:'.Campaign::MIN_AMOUNT, 'max:'.Campaign::MAX_AMOUNT],
            'tenure_months' => ['required', 'integer', Rule::in(Campaign::TENURES)],
            'contract_type' => ['required', 'string', Rule::in([Campaign::CONTRACT_CONVENTIONAL, ...Campaign::SHARIAH_CONTRACTS])],

            // Contract terms: required for their own contract, prohibited for the others.
            'interest_rate_percent' => [
                'required_if:contract_type,'.Campaign::CONTRACT_CONVENTIONAL,
                'prohibited_unless:contract_type,'.Campaign::CONTRACT_CONVENTIONAL,
                'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:'.Campaign::MAX_INTEREST_RATE,
            ],
            'markup_amount' => [
                'required_if:contract_type,'.Campaign::CONTRACT_MURABAHAH,
                'prohibited_unless:contract_type,'.Campaign::CONTRACT_MURABAHAH,
                'nullable', 'numeric', 'decimal:0,2', 'min:0', 'lte:amount_requested',
            ],
            'asset_description' => [
                'required_if:contract_type,'.Campaign::CONTRACT_MURABAHAH,
                'prohibited_unless:contract_type,'.Campaign::CONTRACT_MURABAHAH,
                'nullable', 'string', 'min:5', 'max:255',
            ],
            'investor_profit_share_percent' => [
                'required_if:contract_type,'.Campaign::CONTRACT_MUDARABAH,
                'prohibited_unless:contract_type,'.Campaign::CONTRACT_MUDARABAH,
                'nullable', 'numeric', 'decimal:0,2', 'min:'.Campaign::MIN_PROFIT_SHARE, 'max:'.Campaign::MAX_PROFIT_SHARE,
            ],

            // Financial information (PB-14)
            'annual_income' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:'.CampaignFinancials::MAX_ANNUAL_INCOME],
            'monthly_debt_repayments' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:'.CampaignFinancials::MAX_ANNUAL_INCOME],
            'employment_years' => ['required', 'integer', 'min:0', 'max:'.CampaignFinancials::MAX_EMPLOYMENT_YEARS],
            'housing' => ['required', 'string', Rule::in(array_keys(CampaignFinancials::HOUSING))],

            // Self-declared credit history (PB-14)
            'open_credit_lines' => ['required', 'integer', 'min:0', 'max:'.CampaignFinancials::MAX_COUNT],
            'late_payments_2y' => ['required', 'integer', 'min:0', 'max:'.CampaignFinancials::MAX_COUNT],
            'public_records' => ['required', 'integer', 'min:0', 'max:'.CampaignFinancials::MAX_COUNT],

            // Optional MMA credit report: the same checks as a KYC upload (PB-09).
            'credit_report' => ['nullable', 'file', "mimes:{$extensions}", "extensions:{$extensions}", 'max:'.KycDocument::MAX_KILOBYTES],
        ];
    }

    /**
     * Every message says what is wrong and how to fix it. None mentions interest: the
     * interest-rate field's messages only appear for a conventional request.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $amount = fn (int $value) => 'MVR '.number_format($value);
        $count = 'Enter a whole number from 0 to '.CampaignFinancials::MAX_COUNT.'. Enter 0 if none.';

        return [
            'title.required' => 'Give your request a title.',
            'title.min' => 'Make the title at least 5 characters.',
            'title.max' => 'Keep the title to 150 characters or fewer.',
            'purpose.required' => 'Choose what the financing is for.',
            'purpose.in' => 'Choose what the financing is for from the list.',
            'description.required' => 'Describe what the money will be used for.',
            'description.min' => 'Write at least 30 characters so investors understand the request.',
            'description.max' => 'Keep the description to 5,000 characters or fewer.',
            'business_background.max' => 'Keep the background to 5,000 characters or fewer.',

            'amount_requested.required' => 'Enter the amount you need.',
            'amount_requested.numeric' => 'Enter the amount as a number, like 120000.',
            'amount_requested.decimal' => 'Enter the amount with at most 2 decimal places.',
            'amount_requested.min' => 'The amount must be at least '.$amount(Campaign::MIN_AMOUNT).'.',
            'amount_requested.max' => 'The amount must be '.$amount(Campaign::MAX_AMOUNT).' or less.',
            'tenure_months.required' => 'Choose how many months you need to repay.',
            'tenure_months.integer' => 'Choose a tenure from the list.',
            'tenure_months.in' => 'Choose a tenure from the list.',
            'contract_type.required' => 'Choose a contract type.',
            'contract_type.in' => 'Choose a contract type from the list.',

            'interest_rate_percent.required_if' => 'Enter the yearly interest rate you are offering.',
            'interest_rate_percent.prohibited_unless' => 'Only a conventional request can carry a rate. Remove it for a Murabahah or Mudarabah contract.',
            'interest_rate_percent.numeric' => 'Enter the rate as a number, like 8.5.',
            'interest_rate_percent.decimal' => 'Enter the rate with at most 2 decimal places.',
            'interest_rate_percent.min' => 'The rate can\'t be negative.',
            'interest_rate_percent.max' => 'The rate must be '.Campaign::MAX_INTEREST_RATE.'% or less.',

            'markup_amount.required_if' => 'Enter the fixed markup added to the asset cost.',
            'markup_amount.prohibited_unless' => 'A markup only applies to a Murabahah contract.',
            'markup_amount.numeric' => 'Enter the markup as a number, like 9000.',
            'markup_amount.decimal' => 'Enter the markup with at most 2 decimal places.',
            'markup_amount.min' => 'The markup can\'t be negative.',
            'markup_amount.lte' => 'The markup can\'t be more than the asset cost.',
            'asset_description.required_if' => 'Describe the asset being bought, like "Refrigerated delivery van".',
            'asset_description.prohibited_unless' => 'An asset description only applies to a Murabahah contract.',
            'asset_description.min' => 'Describe the asset in at least 5 characters.',
            'asset_description.max' => 'Keep the asset description to 255 characters or fewer.',

            'investor_profit_share_percent.required_if' => 'Enter the share of the profit that goes to investors.',
            'investor_profit_share_percent.prohibited_unless' => 'A profit share only applies to a Mudarabah contract.',
            'investor_profit_share_percent.numeric' => 'Enter the profit share as a number, like 60.',
            'investor_profit_share_percent.decimal' => 'Enter the profit share with at most 2 decimal places.',
            'investor_profit_share_percent.min' => 'The investors\' share must be between '.Campaign::MIN_PROFIT_SHARE.'% and '.Campaign::MAX_PROFIT_SHARE.'%, so both sides share the profit.',
            'investor_profit_share_percent.max' => 'The investors\' share must be between '.Campaign::MIN_PROFIT_SHARE.'% and '.Campaign::MAX_PROFIT_SHARE.'%, so both sides share the profit.',

            'annual_income.required' => 'Enter your annual income before tax.',
            'annual_income.numeric' => 'Enter your income as a number, like 480000.',
            'annual_income.decimal' => 'Enter your income with at most 2 decimal places.',
            'annual_income.gt' => 'Your annual income must be more than 0.',
            'annual_income.max' => 'Check your annual income: it must be '.$amount(CampaignFinancials::MAX_ANNUAL_INCOME).' or less.',
            'monthly_debt_repayments.required' => 'Enter what you repay each month on existing loans and cards. Enter 0 if nothing.',
            'monthly_debt_repayments.numeric' => 'Enter the monthly repayments as a number, like 6000.',
            'monthly_debt_repayments.decimal' => 'Enter the monthly repayments with at most 2 decimal places.',
            'monthly_debt_repayments.min' => 'Monthly repayments can\'t be negative. Enter 0 if nothing.',
            'monthly_debt_repayments.max' => 'Check your monthly repayments: they must be '.$amount(CampaignFinancials::MAX_ANNUAL_INCOME).' or less.',
            'employment_years.required' => 'Choose how long you have been in your job or running the business.',
            'employment_years.integer' => 'Choose how long you have been in your job or running the business.',
            'employment_years.min' => 'Choose how long you have been in your job or running the business.',
            'employment_years.max' => 'Choose how long you have been in your job or running the business.',
            'housing.required' => 'Choose your housing situation.',
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

            'credit_report.file' => 'Choose a file to upload.',
            'credit_report.uploaded' => 'The file could not be uploaded. '.StoreKycDocumentRequest::TOO_LARGE,
            'credit_report.mimes' => StoreKycDocumentRequest::WRONG_TYPE,
            'credit_report.extensions' => StoreKycDocumentRequest::WRONG_TYPE,
            'credit_report.max' => StoreKycDocumentRequest::TOO_LARGE,
        ];
    }
}
