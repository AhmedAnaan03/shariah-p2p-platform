<?php

namespace App\Support;

use App\Models\Campaign;
use App\Models\CampaignFinancials;

/**
 * Turns a stored financing request into the risk model's features (PB-14): the
 * "deployable + self-declared history" set from PB-12 (risk-service/ml/dataset.py,
 * FEATURE_SETS['self_declared']), with the same names, order and definitions. PB-22 sends
 * this payload to the scoring service and stores it in risk_scores.input_features.
 *
 * The Python side builds the same features from the same application with
 * ml.dataset.platform_features(). Both are checked against one shared fixture,
 * risk-service/tests/fixtures/feature_parity.json, so the two can't drift apart.
 *
 * No interest rate is used anywhere: repayment_burden is principal per month over income per
 * month, so it means the same for conventional, Murabahah and Mudarabah requests.
 *
 * Tenure: LendingClub's loans are only 36 or 60 months, but our form offers 3 to 60
 * (Campaign::TENURES). Tenures other than 36 and 60 are outside the model's training range.
 * They are still sent as plain numbers, and PB-21 must treat term_months as a numeric feature
 * (not a 36/60 category) so the model can place them; see docs/decisions.md, 18 Nov 2026.
 */
final class RiskFeatures
{
    /**
     * The feature names, in the model's order (ml/dataset.py, FEATURE_SETS['self_declared']).
     */
    public const NAMES = [
        'term_months', 'purpose_group', 'loan_to_income', 'repayment_burden', 'dti',
        'emp_length_years', 'home_ownership', 'open_acc', 'delinq_2yrs', 'pub_rec',
    ];

    /**
     * The only terms in the training data.
     */
    public const TRAINED_TENURES = [36, 60];

    /**
     * Our purposes -> the model's purpose groups (ml/dataset.py, PLATFORM_PURPOSES).
     */
    public const PURPOSE_GROUPS = [
        'start_up' => 'business',
        'growth' => 'business',
        'education' => 'education',
        'personal' => 'personal',
    ];

    /**
     * Our housing options -> LendingClub's home_ownership (ml/dataset.py, PLATFORM_HOUSING).
     */
    public const HOME_OWNERSHIP = [
        'rent' => 'RENT',
        'own' => 'OWN',
        'own_with_home_loan' => 'MORTGAGE',
        'family_home' => 'OTHER',
    ];

    /**
     * @return array<string, int|float|string>
     */
    public static function for(Campaign $campaign): array
    {
        return self::fromValues([
            'amount_requested' => $campaign->amount_requested,
            'tenure_months' => $campaign->tenure_months,
            'purpose' => $campaign->purpose,
            ...$campaign->financials->only([
                'monthly_income', 'monthly_debt_repayments', 'employment_years', 'housing',
                'open_credit_lines', 'late_payments_2y', 'public_records',
            ]),
        ]);
    }

    /**
     * The same calculation from plain values (the stored column names), so the parity test can
     * feed it the shared fixture without saving anything.
     *
     * @param  array<string, mixed>  $application
     * @return array<string, int|float|string>
     */
    public static function fromValues(array $application): array
    {
        $amount = (float) $application['amount_requested'];
        $tenure = (int) $application['tenure_months'];
        // Borrowers declare monthly income (PB-58); the model was trained on annual income.
        $monthlyIncome = (float) $application['monthly_income'];
        $annualIncome = CampaignFinancials::annualIncome($monthlyIncome);

        return [
            'term_months' => $tenure,
            'purpose_group' => self::PURPOSE_GROUPS[$application['purpose']],
            'loan_to_income' => $amount / $annualIncome,
            'repayment_burden' => ($amount / $tenure) / $monthlyIncome,
            // LendingClub's dti: monthly debt repayments (excluding housing and the new
            // request) as a percentage of monthly income.
            'dti' => (float) $application['monthly_debt_repayments'] / $monthlyIncome * 100,
            'emp_length_years' => min((int) $application['employment_years'], CampaignFinancials::MAX_EMPLOYMENT_YEARS),
            'home_ownership' => self::HOME_OWNERSHIP[$application['housing']],
            'open_acc' => (int) $application['open_credit_lines'],
            'delinq_2yrs' => (int) $application['late_payments_2y'],
            'pub_rec' => (int) $application['public_records'],
        ];
    }

    /**
     * Whether the request's tenure is one the model never saw in training (anything but 36 or
     * 60 months). Shown to the administrator next to the risk grade later (PB-26).
     */
    public static function tenureOutsideTrainingRange(int $tenureMonths): bool
    {
        return ! in_array($tenureMonths, self::TRAINED_TENURES, true);
    }
}
