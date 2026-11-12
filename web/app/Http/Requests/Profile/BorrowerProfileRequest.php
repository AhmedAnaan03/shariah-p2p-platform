<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\Profile\Concerns\ValidatesPersonalDetails;
use App\Models\BorrowerProfile;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the borrower / SME profile form (PB-09).
 */
class BorrowerProfileRequest extends FormRequest
{
    use ValidatesPersonalDetails;

    /**
     * Only while the account is pending or rejected; once verified the profile is locked.
     */
    public function authorize(): bool
    {
        return $this->user()->canEditVerificationDetails();
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Your account has been verified, so your profile can no longer be changed.');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // exclude_unless: for an individual the business fields are dropped before validation,
        // so they are neither checked nor saved (the controller stores NULL for them).
        $smeOnly = 'exclude_unless:borrower_type,sme';

        return [
            'borrower_type' => ['required', 'string', Rule::in(array_keys(BorrowerProfile::TYPES))],
            ...$this->personalRules(),
            'business_name' => [$smeOnly, 'required', 'string', 'max:255'],
            'business_registration_no' => [$smeOnly, 'required', 'string', 'max:50', 'regex:/^[A-Za-z0-9\/-]+$/'],
            'sector' => [$smeOnly, 'required', 'string', Rule::in(array_keys(BorrowerProfile::SECTORS))],
            'years_operating' => [$smeOnly, 'required', 'integer', 'min:0', 'max:100'],
            // decimal(15,2) in the database: at most 13 digits before the point and 2 after.
            'declared_annual_income' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $type = 'Choose whether you are borrowing as an individual or as a business (SME).';
        $years = 'Enter how many whole years the business has been operating, from 0 to 100.';
        $income = 'Enter your declared annual income or revenue in MVR, like 250000 or 250000.50.';

        return [
            'borrower_type.required' => $type,
            'borrower_type.string' => $type,
            'borrower_type.in' => $type,
            ...$this->personalMessages(),
            'business_name.required' => 'Enter your business name.',
            'business_name.string' => 'Enter your business name.',
            'business_name.max' => 'The business name must be 255 characters or fewer.',
            'business_registration_no.required' => 'Enter your business registration number.',
            'business_registration_no.string' => 'Enter your business registration number.',
            'business_registration_no.max' => 'The registration number must be 50 characters or fewer.',
            'business_registration_no.regex' => 'Use only letters, numbers, / and - in the registration number.',
            'sector.required' => 'Choose your business sector.',
            'sector.string' => 'Choose your business sector.',
            'sector.in' => 'Choose your business sector from the list.',
            'years_operating.required' => $years,
            'years_operating.integer' => $years,
            'years_operating.min' => $years,
            'years_operating.max' => $years,
            'declared_annual_income.required' => $income,
            'declared_annual_income.numeric' => $income,
            'declared_annual_income.decimal' => 'Enter the amount with at most 2 decimal places.',
            'declared_annual_income.min' => 'The amount can\'t be negative.',
            'declared_annual_income.max' => 'The amount is too large.',
        ];
    }

    /**
     * The validated values, ready to save: phone in one format, and the business fields NULL
     * for an individual (so switching from SME to individual clears them).
     *
     * @return array<string, mixed>
     */
    public function profileData(): array
    {
        $data = $this->validated();
        $data['phone'] = self::formatPhone($data['phone']);

        if ($data['borrower_type'] !== 'sme') {
            $data = [...$data, ...array_fill_keys(BorrowerProfile::BUSINESS_FIELDS, null)];
        }

        return $data;
    }
}
