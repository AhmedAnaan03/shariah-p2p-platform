<?php

namespace App\Http\Requests\Repayment;

use App\Support\Money;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Paying off a campaign early (PB-35). `amount` is the payoff total the borrower was shown and
 * confirmed; RepaymentService pays only if it is still the payoff amount under the lock.
 */
class PayOffRequest extends FormRequest
{
    public const INVALID = 'The payoff amount is missing. Reload the page and try again.';

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
            'amount' => ['required', function (string $attribute, mixed $value, Closure $fail) {
                $cents = Money::toCents(is_numeric($value) ? (string) $value : $value);
                if ($cents === null || $cents <= 0) {
                    $fail(self::INVALID);
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['amount.required' => self::INVALID];
    }

    public function cents(): int
    {
        $value = $this->input('amount');

        return (int) Money::toCents(is_numeric($value) ? (string) $value : $value);
    }
}
