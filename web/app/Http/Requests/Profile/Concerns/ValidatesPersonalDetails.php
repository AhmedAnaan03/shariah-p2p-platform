<?php

namespace App\Http\Requests\Profile\Concerns;

/**
 * Rules for the personal fields both profiles share (PB-09 borrower, PB-10 investor): phone,
 * national ID card number and address. Used by BorrowerProfileRequest and
 * InvestorProfileRequest so the two forms can never disagree.
 */
trait ValidatesPersonalDetails
{
    /**
     * Tidy the input before the rules run: people type phone numbers with spaces or dashes
     * ("777 1234", "+960-777-1234") and ID numbers in lower case ("a123456").
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phone'))) {
            $this->merge(['phone' => preg_replace('/[\s-]+/', '', $this->input('phone'))]);
        }

        if (is_string($this->input('national_id_no'))) {
            $this->merge(['national_id_no' => strtoupper(trim($this->input('national_id_no')))]);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    protected function personalRules(): array
    {
        return [
            // A Maldivian number: 7 digits starting 3, 6, 7 or 9, with or without +960.
            'phone' => ['required', 'string', 'regex:/^(\+960)?[3679]\d{6}$/'],
            // Maldivian national ID card: the letter A and six digits.
            'national_id_no' => ['required', 'string', 'regex:/^A\d{6}$/'],
            'address' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function personalMessages(): array
    {
        return [
            'phone.required' => 'Enter your phone number.',
            'phone.string' => 'Enter your phone number.',
            'phone.regex' => 'Enter a 7-digit Maldivian phone number, like 7771234 or +960 7771234.',
            'national_id_no.required' => 'Enter your national ID card number.',
            'national_id_no.string' => 'Enter your national ID card number.',
            'national_id_no.regex' => 'Enter your ID card number as the letter A and 6 digits, like A123456.',
            'address.required' => 'Enter your address.',
            'address.string' => 'Enter your address.',
            'address.max' => 'Your address must be 500 characters or fewer.',
        ];
    }

    /**
     * Store every phone number the same way: "+960 7771234".
     */
    protected static function formatPhone(string $phone): string
    {
        return '+960 '.substr($phone, -7);
    }
}
