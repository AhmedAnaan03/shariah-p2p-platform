<?php

namespace Tests\Feature\Database;

use App\Exceptions\ContractRuleViolation;
use App\Models\Campaign;
use App\Models\RepaymentSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsDatabaseConstraints;
use Tests\TestCase;

/**
 * The RepaymentSchedule model rule (a CHECK can't read campaigns.contract_type):
 * return_due is NULL for Mudarabah and required for conventional and Murabahah.
 */
class RepaymentScheduleContractRuleTest extends TestCase
{
    use AssertsDatabaseConstraints, RefreshDatabase;

    public function test_a_mudarabah_instalment_without_a_return_is_saved(): void
    {
        $schedule = $this->instalmentFor(Campaign::factory()->mudarabah()->create(), returnDue: null);

        $this->assertNull($schedule->fresh()->return_due);
    }

    public function test_a_mudarabah_instalment_with_a_predetermined_return_is_rejected(): void
    {
        $campaign = Campaign::factory()->mudarabah()->create();

        $this->expectException(ContractRuleViolation::class);
        $this->expectExceptionMessage('A Mudarabah instalment cannot have a predetermined return_due');

        try {
            $this->instalmentFor($campaign, returnDue: 500);
        } finally {
            $this->assertDatabaseCount('repayment_schedules', 0);
        }
    }

    public function test_a_mudarabah_return_cannot_be_added_to_a_saved_instalment(): void
    {
        $schedule = $this->instalmentFor(Campaign::factory()->mudarabah()->create(), returnDue: null);

        $this->expectException(ContractRuleViolation::class);

        try {
            $schedule->update(['return_due' => 100]);
        } finally {
            $this->assertNull($schedule->fresh()->return_due);
        }
    }

    public function test_a_murabahah_instalment_with_its_markup_share_is_saved(): void
    {
        $schedule = $this->instalmentFor(Campaign::factory()->murabahah()->create(), returnDue: 333.33);

        $this->assertSame('333.33', $schedule->fresh()->return_due);
    }

    public function test_a_murabahah_instalment_without_a_return_is_rejected(): void
    {
        $campaign = Campaign::factory()->murabahah()->create();

        $this->expectException(ContractRuleViolation::class);
        $this->expectExceptionMessage('A murabahah instalment must state its return_due.');

        $this->instalmentFor($campaign, returnDue: null);
    }

    public function test_a_conventional_instalment_with_its_interest_is_saved(): void
    {
        $schedule = $this->instalmentFor(Campaign::factory()->conventional()->create(), returnDue: 354.17);

        $this->assertSame('354.17', $schedule->fresh()->return_due);
    }

    public function test_a_conventional_instalment_without_a_return_is_rejected(): void
    {
        $campaign = Campaign::factory()->conventional()->create();

        $this->expectException(ContractRuleViolation::class);
        $this->expectExceptionMessage('A conventional instalment must state its return_due.');

        $this->instalmentFor($campaign, returnDue: null);
    }

    public function test_the_same_instalment_number_twice_is_rejected(): void
    {
        $campaign = Campaign::factory()->murabahah()->create();
        $this->instalmentFor($campaign, returnDue: 333.33);

        $this->assertQueryRejected('Duplicate entry', fn () => $this->instalmentFor($campaign, returnDue: 333.33));
    }

    public function test_a_negative_capital_due_is_rejected(): void
    {
        $campaign = Campaign::factory()->murabahah()->create();

        $this->assertCheckViolated('repayment_schedules_capital_due_check', fn () => $this->instalmentFor(
            $campaign, returnDue: 333.33, capitalDue: -1
        ));
    }

    private function instalmentFor(Campaign $campaign, int|float|null $returnDue, int|float $capitalDue = 4166.67): RepaymentSchedule
    {
        return $campaign->repaymentSchedules()->create([
            'instalment_no' => 1,
            'due_date' => '2027-01-31',
            'capital_due' => $capitalDue,
            'return_due' => $returnDue,
        ]);
    }
}
