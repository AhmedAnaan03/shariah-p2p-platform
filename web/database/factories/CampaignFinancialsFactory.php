<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\CampaignFinancials;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: the first applicant in the shared feature-parity fixture
 * (risk-service/tests/fixtures/feature_parity.json).
 *
 * @extends Factory<CampaignFinancials>
 */
class CampaignFinancialsFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'monthly_income' => 40_000,
            'monthly_debt_repayments' => 6_000,
            'employment_years' => 7,
            'housing' => 'own_with_home_loan',
            'open_credit_lines' => 3,
            'late_payments_2y' => 1,
            'public_records' => 0,
        ];
    }
}
