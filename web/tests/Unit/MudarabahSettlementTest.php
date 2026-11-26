<?php

namespace Tests\Unit;

use App\Exceptions\ReportRefused;
use App\Support\MudarabahSettlement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PB-29: what one approved Mudarabah report does to the investors' capital accounts. All figures
 * in cents. Two investors, A (id 1) and B (id 2), with MVR 60,000 and MVR 40,000 invested and a
 * 60 : 40 profit-sharing ratio (investors : business) unless a case says otherwise.
 *
 * The classical rule: a profit first restores genuine losses the investors bore in earlier
 * periods (no profit while the capital is impaired); only what is left is shared at the ratio.
 */
class MudarabahSettlementTest extends TestCase
{
    private const RATIO = 6_000; // 60% in basis points

    /**
     * @return array<int, array{outstanding: int, unrestored: int}>
     */
    private static function accounts(int $a, int $b, int $aLost = 0, int $bLost = 0): array
    {
        return [1 => ['outstanding' => $a, 'unrestored' => $aLost], 2 => ['outstanding' => $b, 'unrestored' => $bLost]];
    }

    /**
     * @return array<int, array{capital: int, restored: int, profit: int, loss: int}>
     */
    private static function parts(array $a, array $b): array
    {
        $keys = ['capital', 'restored', 'profit', 'loss'];

        return [1 => array_combine($keys, $a), 2 => array_combine($keys, $b)];
    }

    public function test_tc1_a_profit_with_no_earlier_loss_is_shared_at_the_ratio_by_capital(): void
    {
        $r = MudarabahSettlement::calculate(self::accounts(60_000_00, 40_000_00), 10_000_00, 0, self::RATIO, null, false);

        $this->assertSame([0, 6_000_00, 4_000_00], [$r['restored'], $r['investorsProfit'], $r['businessProfit']]);
        $this->assertSame(self::parts([0, 0, 3_600_00, 0], [0, 0, 2_400_00, 0]), $r['investors']);
        $this->assertSame(6_000_00, $r['payment']);
        $this->assertSame(100_000_00, $r['outstandingAfter']);
    }

    public function test_tc2_returned_capital_is_split_by_capital_invested(): void
    {
        $r = MudarabahSettlement::calculate(self::accounts(60_000_00, 40_000_00), 0, 33_333_33, self::RATIO, null, false);

        $this->assertSame([20_000_00, 13_333_33], [$r['investors'][1]['capital'], $r['investors'][2]['capital']]);
        $this->assertSame(66_666_67, $r['outstandingAfter']);
    }

    public function test_tc3_a_genuine_loss_is_borne_by_the_investors_in_proportion_to_capital(): void
    {
        $r = MudarabahSettlement::calculate(self::accounts(60_000_00, 40_000_00), -5_000_00, 0, self::RATIO, 'business', false);

        $this->assertSame([5_000_00, 0, 0], [$r['lossBorne'], $r['businessLiable'], $r['payment']]);
        $this->assertSame(self::parts([0, 0, 0, 3_000_00], [0, 0, 0, 2_000_00]), $r['investors']);
        $this->assertSame(95_000_00, $r['outstandingAfter'], 'The loss reduces the capital.');
    }

    public function test_tc4_a_loss_from_negligence_or_misconduct_is_the_business_s_and_leaves_the_capital_whole(): void
    {
        $r = MudarabahSettlement::calculate(self::accounts(60_000_00, 40_000_00), -5_000_00, 0, self::RATIO, 'negligence', false);

        $this->assertSame([0, 5_000_00], [$r['lossBorne'], $r['businessLiable']]);
        $this->assertSame(self::parts([0, 0, 0, 0], [0, 0, 0, 0]), $r['investors']);
        $this->assertSame(100_000_00, $r['outstandingAfter']);
    }

    public function test_tc5_a_later_profit_smaller_than_the_loss_only_restores_capital(): void
    {
        // After TC3: A lost 3,000 and B 2,000. The next period makes MVR 3,000.
        $r = MudarabahSettlement::calculate(self::accounts(57_000_00, 38_000_00, 3_000_00, 2_000_00), 3_000_00, 0, self::RATIO, null, false);

        $this->assertSame([3_000_00, 0, 0], [$r['restored'], $r['investorsProfit'], $r['businessProfit']], 'No profit is shared while capital is impaired.');
        $this->assertSame(self::parts([0, 1_800_00, 0, 0], [0, 1_200_00, 0, 0]), $r['investors']);
        $this->assertSame(3_000_00, $r['payment']);
    }

    public function test_tc6_a_later_profit_larger_than_the_loss_restores_it_first_and_shares_the_rest(): void
    {
        // After TC3, the next period makes MVR 8,000: 5,000 restores the loss, 3,000 is shared.
        $r = MudarabahSettlement::calculate(self::accounts(57_000_00, 38_000_00, 3_000_00, 2_000_00), 8_000_00, 0, self::RATIO, null, false);

        $this->assertSame([5_000_00, 1_800_00, 1_200_00], [$r['restored'], $r['investorsProfit'], $r['businessProfit']]);
        // Profit share by capital: 57 : 38 of 1,800 = 1,080 and 720.
        $this->assertSame(self::parts([0, 3_000_00, 1_080_00, 0], [0, 2_000_00, 720_00, 0]), $r['investors']);
        $this->assertSame(6_800_00, $r['payment']);
    }

    public function test_tc7_restoration_never_gives_an_investor_more_than_they_lost(): void
    {
        // Uneven losses with awkward remainders: 0.01, 0.02 and 3.33 lost, 1.00 restored.
        $accounts = [
            1 => ['outstanding' => 100_00, 'unrestored' => 1],
            2 => ['outstanding' => 100_00, 'unrestored' => 2],
            3 => ['outstanding' => 100_00, 'unrestored' => 333],
        ];
        $r = MudarabahSettlement::calculate($accounts, 1_00, 0, self::RATIO, null, false);

        $this->assertSame(1_00, $r['restored']);
        foreach ($accounts as $id => $account) {
            $this->assertLessThanOrEqual($account['unrestored'], $r['investors'][$id]['restored']);
        }
        $this->assertSame(1_00, array_sum(array_column($r['investors'], 'restored')));
    }

    public function test_tc8_the_investors_share_is_rounded_down_and_the_business_keeps_the_laari_left_over(): void
    {
        // 60% of MVR 100.01 is 60.006: investors 60.00, business 40.01. Split three ways: 20.00 each.
        $accounts = [1 => ['outstanding' => 1_000_00, 'unrestored' => 0], 2 => ['outstanding' => 1_000_00, 'unrestored' => 0], 3 => ['outstanding' => 1_000_00, 'unrestored' => 0]];
        $r = MudarabahSettlement::calculate($accounts, 100_01, 0, self::RATIO, null, false);

        $this->assertSame([60_00, 40_01], [$r['investorsProfit'], $r['businessProfit']]);
        $this->assertSame([20_00, 20_00, 20_00], array_column($r['investors'], 'profit'));
    }

    public function test_tc9_a_genuine_loss_larger_than_the_capital_left_reduces_it_only_to_zero(): void
    {
        $r = MudarabahSettlement::calculate(self::accounts(600_00, 400_00), -5_000_00, 0, self::RATIO, 'business', false);

        $this->assertSame(1_000_00, $r['lossBorne'], 'Investors never lose more than their capital.');
        $this->assertSame(0, $r['outstandingAfter']);
    }

    public function test_tc10_the_final_period_settles_with_the_capital_returned_and_a_genuine_loss(): void
    {
        $r = MudarabahSettlement::calculate(self::accounts(60_000_00, 40_000_00), -10_000_00, 90_000_00, self::RATIO, 'business', true);

        $this->assertSame([90_000_00, 10_000_00, 0], [$r['capital'], $r['lossBorne'], $r['outstandingAfter']]);
        $this->assertSame(self::parts([54_000_00, 0, 0, 6_000_00], [36_000_00, 0, 0, 4_000_00]), $r['investors']);
    }

    /**
     * @return array<string, array{0: array<int, array<string, int>>, 1: int, 2: int, 3: string|null, 4: bool, 5: string}>
     */
    public static function refusals(): array
    {
        $accounts = self::accounts(60_000_00, 40_000_00);

        return [
            'TC11 a loss with no cause chosen' => [$accounts, -1_00, 0, null, false, 'Choose whether the loss was a genuine business loss or caused by negligence or misconduct.'],
            'TC12 more capital than is invested' => [$accounts, 0, 100_000_01, null, false, 'The capital returned can be at most the capital still invested, MVR 100,000.00.'],
            'TC13 a final period that leaves capital invested' => [$accounts, 1_000_00, 50_000_00, null, true, 'This is the final period, so it must settle the capital: MVR 50,000.00 would still be invested. The business returns all remaining capital, less any genuine loss.'],
            'TC14 a final negligence loss without all capital returned' => [$accounts, -10_000_00, 90_000_00, 'negligence', true, 'This is the final period, so it must settle the capital: MVR 10,000.00 would still be invested. The business returns all remaining capital, less any genuine loss.'],
        ];
    }

    #[DataProvider('refusals')]
    public function test_refused_reports(array $accounts, int $profit, int $capital, ?string $cause, bool $final, string $message): void
    {
        $this->expectException(ReportRefused::class);
        $this->expectExceptionMessage($message);
        MudarabahSettlement::calculate($accounts, $profit, $capital, self::RATIO, $cause, $final);
    }

    public function test_tc15_the_same_report_always_gives_the_same_split(): void
    {
        $accounts = [3 => ['outstanding' => 333_33, 'unrestored' => 0], 1 => ['outstanding' => 333_33, 'unrestored' => 0], 2 => ['outstanding' => 333_34, 'unrestored' => 0]];
        $first = MudarabahSettlement::calculate($accounts, 100_00, 1_00, self::RATIO, null, false);

        $this->assertSame($first, MudarabahSettlement::calculate($accounts, 100_00, 1_00, self::RATIO, null, false));
        $this->assertSame(60_00, array_sum(array_column($first['investors'], 'profit')));
        $this->assertSame(1_00, array_sum(array_column($first['investors'], 'capital')));
    }
}
