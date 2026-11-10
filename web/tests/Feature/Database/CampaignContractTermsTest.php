<?php

namespace Tests\Feature\Database;

use App\Models\Campaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\AssertsDatabaseConstraints;
use Tests\TestCase;

/**
 * The campaigns CHECK constraints: each contract's terms are required for that contract and
 * NULL for the others, so a Shariah campaign can never hold an interest rate.
 */
class CampaignContractTermsTest extends TestCase
{
    use AssertsDatabaseConstraints, RefreshDatabase;

    /**
     * [factory state, attribute overrides, CHECK expected to reject it (null = valid)]
     */
    public static function contractTerms(): array
    {
        $conventional = 'campaigns_conventional_terms_check';
        $murabahah = 'campaigns_murabahah_terms_check';
        $mudarabah = 'campaigns_mudarabah_terms_check';

        return [
            // Conventional
            'conventional with an 8.50% interest rate' => ['conventional', [], null],
            'conventional with a 0% interest rate' => ['conventional', ['interest_rate_percent' => 0], null],
            'conventional with a blank interest rate' => ['conventional', ['interest_rate_percent' => null], $conventional],
            'conventional with a negative interest rate' => ['conventional', ['interest_rate_percent' => -1], $conventional],
            'conventional with a markup and asset' => ['conventional', ['markup_amount' => 1000, 'asset_description' => 'Van'], $murabahah],
            'conventional with a profit share' => ['conventional', ['investor_profit_share_percent' => 50], $mudarabah],

            // Murabahah
            'murabahah with markup and asset' => ['murabahah', [], null],
            'murabahah with an interest rate' => ['murabahah', ['interest_rate_percent' => 5], $conventional],
            'murabahah with a blank markup' => ['murabahah', ['markup_amount' => null], $murabahah],
            'murabahah with a blank asset description' => ['murabahah', ['asset_description' => null], $murabahah],
            'murabahah with a negative markup' => ['murabahah', ['markup_amount' => -100], $murabahah],
            'murabahah with a profit share' => ['murabahah', ['investor_profit_share_percent' => 50], $mudarabah],

            // Mudarabah
            'mudarabah with a 60% profit share' => ['mudarabah', [], null],
            'mudarabah with a 99.99% profit share' => ['mudarabah', ['investor_profit_share_percent' => 99.99], null],
            'mudarabah with an interest rate' => ['mudarabah', ['interest_rate_percent' => 5], $conventional],
            'mudarabah with a markup and asset' => ['mudarabah', ['markup_amount' => 1000, 'asset_description' => 'Van'], $murabahah],
            'mudarabah with a blank profit share' => ['mudarabah', ['investor_profit_share_percent' => null], $mudarabah],
            'mudarabah with a 0% profit share' => ['mudarabah', ['investor_profit_share_percent' => 0], $mudarabah],
            'mudarabah with a 100% profit share' => ['mudarabah', ['investor_profit_share_percent' => 100], $mudarabah],

            // Any contract
            'amount requested of 0' => ['conventional', ['amount_requested' => 0], 'campaigns_amount_requested_check'],
            'tenure of 0 months' => ['murabahah', ['tenure_months' => 0], 'campaigns_tenure_months_check'],
        ];
    }

    #[DataProvider('contractTerms')]
    public function test_contract_terms_are_checked_by_the_database(string $contract, array $overrides, ?string $rejectedBy): void
    {
        $factory = Campaign::factory()->{$contract}();

        if ($rejectedBy !== null) {
            $this->assertCheckViolated($rejectedBy, fn () => $factory->create($overrides));
            $this->assertDatabaseCount('campaigns', 0);

            return;
        }

        $campaign = $factory->create($overrides);
        $this->assertDatabaseHas('campaigns', ['id' => $campaign->id, 'contract_type' => $contract]);
    }

    public function test_an_interest_rate_cannot_be_added_to_a_saved_shariah_campaign(): void
    {
        $campaign = Campaign::factory()->murabahah()->create();

        $this->assertCheckViolated('campaigns_conventional_terms_check', function () use ($campaign) {
            $campaign->update(['interest_rate_percent' => 4.5]);
        });

        $this->assertNull($campaign->fresh()->interest_rate_percent);
    }

    public function test_shariah_mode_follows_from_the_contract_type(): void
    {
        $this->assertFalse(Campaign::factory()->conventional()->make()->isShariah());
        $this->assertTrue(Campaign::factory()->murabahah()->make()->isShariah());
        $this->assertTrue(Campaign::factory()->mudarabah()->make()->isShariah());
    }

    public function test_a_new_campaign_starts_as_a_draft(): void
    {
        $campaign = Campaign::factory()->create();

        $this->assertSame('draft', $campaign->fresh()->status);
    }
}
