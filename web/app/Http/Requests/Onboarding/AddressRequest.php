<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the address step of the onboarding wizard (PB-53), for borrowers and investors:
 * house or building name, road (magu), atoll, island and postcode.
 *
 * The atoll is only asked for so the island list can be filtered; what is stored is the island,
 * which must belong to the chosen atoll. The postcode is the Maldivian 5-digit code.
 */
class AddressRequest extends FormRequest
{
    /**
     * Only while the account is pending or rejected; once verified the details are locked.
     */
    public function authorize(): bool
    {
        return $this->user()->canEditVerificationDetails();
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Your account has been verified, so your address can no longer be changed.');
    }

    /**
     * People type postcodes with spaces ("20 026"); names get stray spaces trimmed by Laravel.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('postcode'))) {
            $this->merge(['postcode' => preg_replace('/\s+/', '', $this->input('postcode'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'address_house' => ['required', 'string', 'max:100'],
            'address_road' => ['required', 'string', 'max:100'],
            'atoll_id' => ['required', 'integer', Rule::exists('atolls', 'id')],
            // bail: once the atoll is missing or the island isn't a number, the "belongs to the
            // atoll" check would only repeat the problem.
            'island_id' => [
                'bail', 'required', 'integer',
                Rule::exists('islands', 'id')->where('atoll_id', (int) $this->input('atoll_id')),
            ],
            'postcode' => ['required', 'string', 'regex:/^\d{5}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $island = 'Choose your island from the list for the atoll you picked.';
        $postcode = 'Enter your 5-digit postcode, like 20026.';

        return [
            'address_house.required' => 'Enter your house or building name.',
            'address_house.string' => 'Enter your house or building name.',
            'address_house.max' => 'The house or building name must be 100 characters or fewer.',
            'address_road.required' => 'Enter the road (magu) your home is on.',
            'address_road.string' => 'Enter the road (magu) your home is on.',
            'address_road.max' => 'The road name must be 100 characters or fewer.',
            'atoll_id.required' => 'Choose your atoll.',
            'atoll_id.integer' => 'Choose your atoll from the list.',
            'atoll_id.exists' => 'Choose your atoll from the list.',
            'island_id.required' => 'Choose your island.',
            'island_id.integer' => $island,
            'island_id.exists' => $island,
            'postcode.required' => $postcode,
            'postcode.string' => $postcode,
            'postcode.regex' => $postcode,
        ];
    }

    /**
     * The values to save on the profile: the island (which implies the atoll), not the atoll.
     *
     * @return array<string, mixed>
     */
    public function addressData(): array
    {
        return $this->safe()->only(['address_house', 'address_road', 'island_id', 'postcode']);
    }
}
