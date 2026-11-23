<?php

namespace App\Models;

use Database\Factories\CampaignFinancialsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The borrower's financial information for one financing request (PB-14): the fields the risk
 * model uses, as structured values rather than files. RiskFeatures turns them, with the
 * request's amount, tenure and purpose, into the model's features.
 *
 * Since PB-58 it is a snapshot of the borrower's verified financial profile, copied when the
 * request is submitted, so the request is scored on what was true at the time even if the
 * profile changes later. Income is stored monthly, as declared; RiskFeatures works out the
 * annual figure the model was trained on.
 */
#[Fillable([
    'monthly_income', 'monthly_debt_repayments', 'employment_years', 'housing',
    'open_credit_lines', 'late_payments_2y', 'public_records',
])]
class CampaignFinancials extends Model
{
    /** @use HasFactory<CampaignFinancialsFactory> */
    use HasFactory;

    protected $table = 'campaign_financials';

    /**
     * Housing options, stored as the key. "Family home" is common in the Maldives and has no
     * LendingClub equivalent; RiskFeatures maps it to OTHER (PB-12).
     */
    public const HOUSING = [
        'rent' => 'Rent',
        'own' => 'Own, with no home loan',
        'own_with_home_loan' => 'Own, with a home loan',
        'family_home' => 'Live in a family home',
    ];

    /**
     * Years in the current job or running the business: 0 means less than one year and 10 means
     * ten or more, as LendingClub records it ("< 1 year" ... "10+ years").
     */
    public const MAX_EMPLOYMENT_YEARS = 10;

    /**
     * Upper limits that catch typing mistakes, not real rules.
     */
    public const MAX_MONTHLY_INCOME = 10_000_000;

    public const MAX_COUNT = 99;

    protected function casts(): array
    {
        return [
            'monthly_income' => 'decimal:2',
            'monthly_debt_repayments' => 'decimal:2',
            'employment_years' => 'integer',
            'open_credit_lines' => 'integer',
            'late_payments_2y' => 'integer',
            'public_records' => 'integer',
        ];
    }

    /**
     * "Less than 1 year", "1 year", "7 years", "10 years or more".
     */
    public static function employmentLabel(int $years): string
    {
        return match (true) {
            $years <= 0 => 'Less than 1 year',
            $years === 1 => '1 year',
            $years >= self::MAX_EMPLOYMENT_YEARS => self::MAX_EMPLOYMENT_YEARS.' years or more',
            default => "{$years} years",
        };
    }

    /**
     * Annual income for the risk model: the declared monthly income × 12.
     */
    public static function annualIncome(float|string $monthlyIncome): float
    {
        return (float) $monthlyIncome * 12;
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
