<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\Profile\Concerns\ValidatesPersonalDetails;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the investor profile form (PB-10): the same personal details as a borrower.
 * The Shariah-only toggle (shariah_only) belongs to PB-31 and is not part of this form.
 */
class InvestorProfileRequest extends FormRequest
{
    use ValidatesPersonalDetails;

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
        return $this->personalRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->personalMessages();
    }

    /**
     * @return array<string, mixed>
     */
    public function profileData(): array
    {
        $data = $this->validated();
        $data['phone'] = self::formatPhone($data['phone']);

        return $data;
    }
}
