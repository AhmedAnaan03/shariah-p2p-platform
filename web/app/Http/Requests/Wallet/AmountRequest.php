<?php

namespace App\Http\Requests\Wallet;

use App\Support\Money;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * The amount of a money movement (PB-18, PB-19): an amount in MVR with at most two decimals,
 * between a minimum and a maximum that each request works out. Only verified users can move
 * money, like pledging (the 'move-money' gate).
 *
 * The final word is always the ledger's, under its locks: these checks give a clear message
 * next to the field, but a balance can still change between them and the transfer.
 */
abstract class AmountRequest extends FormRequest
{
    public const INVALID = 'Enter an amount in MVR, like 5000 or 5000.50.';

    public function authorize(): bool
    {
        return Gate::allows('move-money');
    }

    /**
     * [lowest cents, message], [highest cents, message]
     *
     * @return array{0: array{0: int, 1: string}, 1: array{0: int, 1: string}}
     */
    abstract protected function limits(): array;

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

                    return;
                }
                [[$min, $tooSmall], [$max, $tooBig]] = $this->limits();
                if ($cents < $min) {
                    $fail($tooSmall);
                } elseif ($cents > $max) {
                    $fail($tooBig);
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['amount.required' => 'Enter an amount.'];
    }

    public function cents(): int
    {
        $value = $this->input('amount');

        return (int) Money::toCents(is_numeric($value) ? (string) $value : $value);
    }
}
