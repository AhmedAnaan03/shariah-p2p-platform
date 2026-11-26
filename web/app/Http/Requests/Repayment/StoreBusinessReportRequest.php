<?php

namespace App\Http\Requests\Repayment;

use App\Http\Requests\Kyc\StoreKycDocumentRequest;
use App\Models\KycDocument;
use App\Support\Money;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A Mudarabah business report (PB-29): revenue, costs and the capital returned, each an amount
 * in MVR (0 allowed), and an optional supporting document (PDF, JPG or PNG up to 5 MB, the
 * same rule as every document). The period, the planned date and the limits on capital are
 * checked by BusinessReportService under the campaign's lock.
 */
class StoreBusinessReportRequest extends FormRequest
{
    public const INVALID = 'Enter an amount in MVR, like 5000 or 5000.50. Enter 0 if there was none.';

    public const MAX_CENTS = 100_000_000_00;

    public function authorize(): bool
    {
        return true; // the borrower route group and the controller's ownership check
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $amount = function (string $attribute, mixed $value, Closure $fail) {
            $cents = Money::toCents(is_numeric($value) ? (string) $value : $value);
            if ($cents === null || $cents < 0) {
                $fail(self::INVALID);
            } elseif ($cents > self::MAX_CENTS) {
                $fail('Enter an amount up to '.Money::format(self::MAX_CENTS).'.');
            }
        };

        return [
            'revenue' => ['required', $amount],
            'costs' => ['required', $amount],
            'capital_returned' => ['required', $amount],
            'document' => ['nullable', 'file', 'mimes:'.implode(',', KycDocument::ALLOWED_EXTENSIONS), 'max:'.KycDocument::MAX_KILOBYTES],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'revenue.required' => 'Enter the period’s revenue. Enter 0 if there was none.',
            'costs.required' => 'Enter the period’s costs. Enter 0 if there were none.',
            'capital_returned.required' => 'Enter the capital you are returning. Enter 0 if none this period.',
            'document.file' => 'Choose a file to upload.',
            'document.mimes' => 'The document must be a PDF, JPG or PNG file.',
            'document.max' => StoreKycDocumentRequest::TOO_LARGE,
            'document.uploaded' => 'The file could not be uploaded. '.StoreKycDocumentRequest::TOO_LARGE,
        ];
    }

    /**
     * @return array{revenue: int, costs: int, capital_returned: int}
     */
    public function cents(): array
    {
        $cents = fn (string $key) => (int) Money::toCents(is_numeric($this->input($key)) ? (string) $this->input($key) : $this->input($key));

        return ['revenue' => $cents('revenue'), 'costs' => $cents('costs'), 'capital_returned' => $cents('capital_returned')];
    }
}
