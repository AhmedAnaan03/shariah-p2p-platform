<?php

namespace Tests\Feature\Database;

use App\Exceptions\ContractRuleViolation;
use App\Models\Campaign;
use App\Models\RepaymentSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\AssertsDatabaseConstraints;
use Tests\TestCase;

/**
 * The two-way return_due rule in the RepaymentSchedule model (a CHECK can't read
 * campaigns.contract_type): NULL for Mudarabah; required and zero or more for conventional
 * and Murabahah.
 */
class RepaymentScheduleContractRuleTest extends TestCase
{
    use AssertsDatabaseConstraints, RefreshDatabase;

    /**
     * [contract, return_due, expected: null = saved, otherwise the ContractRuleViolation message]
     */
    public static function returnDueRule(): array
    {
        $predetermined = 'A Mudarabah instalment cannot have a predetermined return_due';

        return [
            // Mudarabah: NULL only
            'mudarabah, return_due blank' => ['mudarabah', null, null],
            'mudarabah, return_due 500' => ['mudarabah', 500, $predetermined],
            'mudarabah, return_due 0' => ['mudarabah', 0, $predetermined],

            // Murabahah: required, zero or more
            'murabahah, return_due 333.33' => ['murabahah', 333.33, null],
            'murabahah, return_due 0' => ['murabahah', 0, null],
            'murabahah, return_due blank' => ['murabahah', null, 'A murabahah instalment must state its return_due.'],
            'murabahah, return_due -0.01' => ['murabahah', -0.01, "A murabahah instalment's return_due must be zero or more."],

            // Conventional: required, zero or more
            'conventional, return_due 354.17' => ['conventional', 354.17, null],
            'conventional, return_due 0' => ['conventional', 0, null],
            'conventional, return_due blank' => ['conventional', null, 'A conventional instalment must state its return_due.'],
            'conventional, return_due -1' => ['conventional', -1, "A conventional instalment's return_due must be zero or more."],
        ];
    }

    #[DataProvider('returnDueRule')]
    public function test_return_due_must_match_the_contract(string $contract, int|float|null $returnDue, ?string $violation): void
    {
        $campaign = Campaign::factory()->{$contract}()->create();

        if ($violation === null) {
            $schedule = $this->instalmentFor($campaign, $returnDue);

            $expected = $returnDue === null ? null : number_format($returnDue, 2, '.', '');
            $this->assertSame($expected, $schedule->fresh()->return_due);

            return;
        }

        try {
            $this->instalmentFor($campaign, $returnDue);
            $this->fail('Expected a ContractRuleViolation, but the instalment was saved.');
        } catch (ContractRuleViolation $e) {
            $this->assertStringContainsString($violation, $e->getMessage());
        }

        $this->assertDatabaseCount('repayment_schedules', 0);
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

    public function test_a_raw_insert_bypassing_the_model_still_cannot_store_a_negative_return(): void
    {
        // DB::table() skips model events, so only the database CHECK stands in the way.
        $campaign = Campaign::factory()->murabahah()->create();

        $this->assertCheckViolated('repayment_schedules_return_due_check', fn () => DB::table('repayment_schedules')->insert([
            'campaign_id' => $campaign->id, 'instalment_no' => 1, 'due_date' => '2027-01-31',
            'capital_due' => 4166.67, 'return_due' => -1,
        ]));
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
