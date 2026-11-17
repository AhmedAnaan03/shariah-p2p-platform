<?php

namespace Tests\Feature\Database;

use App\Models\Campaign;
use App\Models\CampaignFinancials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\AssertsDatabaseConstraints;
use Tests\TestCase;

/**
 * PB-14: the database itself refuses impossible financial information, even if a write
 * skipped the form request.
 */
class CampaignFinancialsConstraintsTest extends TestCase
{
    use AssertsDatabaseConstraints, RefreshDatabase;

    /**
     * [attribute overrides, the error MySQL must give (null = saved)]
     *
     * @return array<string, array{0: array<string, mixed>, 1: ?string}>
     */
    public static function values(): array
    {
        return [
            'valid row' => [[], null],
            'no existing debt' => [['monthly_debt_repayments' => 0], null],
            'ten years or more' => [['employment_years' => 10], null],
            'zero income' => [['annual_income' => 0], "Check constraint 'campaign_financials_annual_income_check' is violated"],
            'negative income' => [['annual_income' => -1], "Check constraint 'campaign_financials_annual_income_check' is violated"],
            'negative monthly debt' => [['monthly_debt_repayments' => -1], "Check constraint 'campaign_financials_monthly_debt_check' is violated"],
            '11 years in the job' => [['employment_years' => 11], "Check constraint 'campaign_financials_employment_years_check' is violated"],
            'negative count' => [['late_payments_2y' => -1], 'Out of range value'],
            'unknown housing' => [['housing' => 'castle'], 'Data truncated'],
            'blank income' => [['annual_income' => null], 'cannot be null'],
        ];
    }

    #[DataProvider('values')]
    public function test_financial_values_are_checked_by_the_database(array $overrides, ?string $error): void
    {
        $write = fn () => CampaignFinancials::factory()->create($overrides);

        if ($error === null) {
            $write();
            $this->assertSame(1, CampaignFinancials::count());
        } else {
            $this->assertQueryRejected($error, $write);
        }
    }

    public function test_a_campaign_has_at_most_one_financials_row(): void
    {
        $campaign = Campaign::factory()->submitted()->create();

        $this->assertQueryRejected('Duplicate entry', fn () => CampaignFinancials::factory()->for($campaign)->create());
    }
}
