<?php

namespace Tests\Feature\Repayments;

use App\Exceptions\ContractRuleViolation;
use App\Models\Campaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MakesWallets;
use Tests\TestCase;

/**
 * PB-28: a Murabahah's markup is disclosed and fixed. Once the campaign is funded, the contract
 * is agreed, and the Campaign model refuses any change to the markup or the other terms that set
 * the sale price.
 */
class MarkupLockTest extends TestCase
{
    use MakesWallets, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_before_funding_the_markup_can_still_be_set(): void
    {
        $campaign = Campaign::factory()->murabahah()->live()->create(['markup_amount' => 4_000]);

        $campaign->update(['markup_amount' => 4_500]);

        $this->assertSame('4500.00', (string) $campaign->fresh()->markup_amount);
    }

    public function test_after_funding_the_markup_cannot_change(): void
    {
        [$campaign] = $this->paidOutCampaign('murabahah', 10_000, 3, [10_000], ['markup_amount' => 1_000]);

        try {
            $campaign->update(['markup_amount' => 2_000]);
            $this->fail('Changing a funded Murabahah markup must be refused.');
        } catch (ContractRuleViolation $e) {
            $this->assertStringContainsString('markup_amount', $e->getMessage());
        }

        $this->assertSame('1000.00', (string) $campaign->fresh()->markup_amount);
    }

    public function test_setting_the_same_markup_again_is_not_a_change(): void
    {
        [$campaign] = $this->paidOutCampaign('murabahah', 10_000, 3, [10_000], ['markup_amount' => 1_000]);

        $campaign->forceFill(['markup_amount' => '1000.00', 'status_reason' => 'A note'])->save();

        $this->assertSame('A note', $campaign->fresh()->status_reason);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: mixed}>
     */
    public static function lockedTerms(): array
    {
        return [
            'Murabahah markup' => ['murabahah', 'markup_amount', 0],
            'Murabahah asset cost' => ['murabahah', 'amount_requested', 12_000],
            'Murabahah asset' => ['murabahah', 'asset_description', 'A different asset'],
            'tenure' => ['murabahah', 'tenure_months', 6],
            'contract type' => ['murabahah', 'contract_type', 'conventional'],
            'conventional rate' => ['conventional', 'interest_rate_percent', 12],
            'Mudarabah profit share' => ['mudarabah', 'investor_profit_share_percent', 80],
        ];
    }

    #[DataProvider('lockedTerms')]
    public function test_no_agreed_term_can_change_after_funding(string $contract, string $term, mixed $value): void
    {
        [$campaign] = $this->paidOutCampaign($contract, 10_000, 3, [10_000]);
        $before = $campaign->getRawOriginal($term);

        $this->expectException(ContractRuleViolation::class);
        try {
            $campaign->forceFill([$term => $value])->save();
        } finally {
            $this->assertSame($before, $campaign->fresh()->getRawOriginal($term), 'Nothing was saved.');
        }
    }

    public function test_the_workflow_can_still_change_a_funded_campaigns_status(): void
    {
        [$campaign] = $this->paidOutCampaign();

        $campaign->forceFill(['status' => 'completed'])->save();

        $this->assertSame('completed', $campaign->fresh()->status);
    }
}
