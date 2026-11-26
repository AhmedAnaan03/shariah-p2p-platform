<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Support\ScheduleGenerator;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PB-34, PB-28: the schedule maths, in whole cents. Every schedule must add up exactly: the
 * capital to the amount, a Murabahah to cost + markup, and a Mudarabah has no return figure.
 *
 * Laravel's TestCase (not plain PHPUnit) only because forCampaign() takes a Campaign model; no
 * database is used.
 */
class ScheduleGeneratorTest extends TestCase
{
    /**
     * @return array<string, array{0: int, 1: int, 2: list<int>}>
     */
    public static function evenSplits(): array
    {
        return [
            'blank: zero splits into zeros' => [0, 3, [0, 0, 0]],
            'divides exactly' => [5_400_000, 12, array_fill(0, 12, 450_000)],
            'leftover cents go on the last part' => [1_000_000, 3, [333_333, 333_333, 333_334]],
            'one part' => [12_345, 1, [12_345]],
            'fewer cents than parts' => [2, 3, [0, 0, 2]],
        ];
    }

    #[DataProvider('evenSplits')]
    public function test_an_even_split_always_adds_up_to_the_total(int $total, int $parts, array $expected): void
    {
        $split = ScheduleGenerator::evenSplit($total, $parts);

        $this->assertSame($expected, $split);
        $this->assertSame($total, array_sum($split));
    }

    /**
     * @return array<string, array{0: int, 1: int}>
     */
    public static function invalidSplits(): array
    {
        return [
            'no months' => [1_000, 0],
            'negative months' => [1_000, -2],
            'negative amount' => [-1, 3],
        ];
    }

    #[DataProvider('invalidSplits')]
    public function test_an_invalid_split_is_refused(int $total, int $parts): void
    {
        $this->expectException(InvalidArgumentException::class);
        ScheduleGenerator::evenSplit($total, $parts);
    }

    public function test_a_conventional_loan_is_amortised_and_its_capital_adds_up_exactly(): void
    {
        // MVR 10,000 at 12% a year over 12 months: r = 1% a month, payment MVR 888.49.
        [$capital, $interest] = ScheduleGenerator::amortised(1_000_000, 12.0, 12);

        $this->assertSame(1_000_000, array_sum($capital), 'Capital adds up to the amount.');
        $this->assertSame([78_849, 10_000], [$capital[0], $interest[0]], 'Month 1: 1% interest on MVR 10,000.');
        $this->assertSame(88_849, $capital[1] + $interest[1], 'Every month but the last is the same payment.');
        // 12 × MVR 888.49 = MVR 10,661.88, less 2 laari on the last instalment.
        $this->assertSame(66_186, array_sum($interest), 'Total interest MVR 661.86.');
        $this->assertSame(88_847, $capital[11] + $interest[11], 'The last instalment repays exactly what is left.');
        $this->assertGreaterThan($interest[11], $interest[0], 'Interest falls as the balance is repaid.');
        foreach (array_map(null, $capital, $interest) as [$c, $i]) {
            $this->assertGreaterThanOrEqual(0, $c);
            $this->assertGreaterThanOrEqual(0, $i);
        }
    }

    public function test_the_last_conventional_instalment_absorbs_the_rounding(): void
    {
        // MVR 25,000 at 8.5% over 36 months: the rounded payment never divides exactly.
        [$capital, $interest] = ScheduleGenerator::amortised(2_500_000, 8.5, 36);
        $payments = array_map(fn ($c, $i) => $c + $i, $capital, $interest);

        $this->assertSame(2_500_000, array_sum($capital));
        $this->assertCount(1, array_unique(array_slice($payments, 0, 35)), 'The first 35 payments are equal.');
        $this->assertLessThan(100, abs($payments[35] - $payments[0]), 'The last differs by under a rufiyaa.');
    }

    public function test_a_zero_rate_loan_is_the_amount_split_evenly_with_no_interest(): void
    {
        [$capital, $interest] = ScheduleGenerator::amortised(1_000_000, 0.0, 3);

        $this->assertSame([333_333, 333_333, 333_334], $capital);
        $this->assertSame([0, 0, 0], $interest);
    }

    public function test_a_negative_rate_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ScheduleGenerator::amortised(1_000_000, -1.0, 12);
    }

    public function test_a_murabahah_is_cost_plus_markup_divided_by_the_tenure_and_adds_up_to_the_sale_price(): void
    {
        $campaign = $this->campaign(Campaign::CONTRACT_MURABAHAH, ['amount_requested' => '10000.00', 'markup_amount' => '1000.00', 'tenure_months' => 3]);

        $rows = ScheduleGenerator::forCampaign($campaign, Carbon::parse('2026-11-26 10:00', Campaign::LOCAL_TIMEZONE));

        $this->assertSame([333_333, 333_333, 333_334], array_column($rows, 'capital_cents'));
        $this->assertSame([33_333, 33_333, 33_334], array_column($rows, 'return_cents'));
        $this->assertSame(1_100_000, array_sum(array_column($rows, 'capital_cents')) + array_sum(array_column($rows, 'return_cents')), 'Exactly cost + markup.');
        $this->assertSame([366_666, 366_666, 366_668], array_map(fn ($r) => $r['capital_cents'] + $r['return_cents'], $rows));
    }

    public function test_a_murabahah_that_divides_exactly_has_equal_instalments(): void
    {
        $campaign = $this->campaign(Campaign::CONTRACT_MURABAHAH, ['amount_requested' => '50000.00', 'markup_amount' => '4000.00', 'tenure_months' => 12]);

        $rows = ScheduleGenerator::forCampaign($campaign, Carbon::parse('2026-11-26 10:00', Campaign::LOCAL_TIMEZONE));

        $this->assertSame(array_fill(0, 12, 450_000), array_map(fn ($r) => $r['capital_cents'] + $r['return_cents'], $rows), 'Twelve instalments of exactly MVR 4,500.00.');
        // MVR 4,000 ÷ 12 = 333.33…: the 4 laari left over go on the first four instalments.
        $this->assertSame([...array_fill(0, 4, 33_334), ...array_fill(0, 8, 33_333)], array_column($rows, 'return_cents'));
        $this->assertSame(400_000, array_sum(array_column($rows, 'return_cents')), 'The markup adds up exactly.');
        $this->assertSame(5_000_000, array_sum(array_column($rows, 'capital_cents')), 'The capital adds up to the asset cost.');
    }

    public function test_a_murabahah_with_no_markup_is_the_cost_split_evenly(): void
    {
        [$capital, $markup] = ScheduleGenerator::murabahah(1_000_000, 0, 3);

        $this->assertSame([333_333, 333_333, 333_334], $capital);
        $this->assertSame([0, 0, 0], $markup);
    }

    public function test_a_mudarabah_plans_capital_returns_only_with_no_return_figure(): void
    {
        $campaign = $this->campaign(Campaign::CONTRACT_MUDARABAH, ['amount_requested' => '20000.00', 'investor_profit_share_percent' => '60.00', 'tenure_months' => 6]);

        $rows = ScheduleGenerator::forCampaign($campaign, Carbon::parse('2026-11-26 10:00', Campaign::LOCAL_TIMEZONE));

        $this->assertSame(array_fill(0, 6, null), array_column($rows, 'return_cents'), 'No predetermined return: NULL, not 0.');
        $this->assertSame([333_333, 333_333, 333_333, 333_333, 333_333, 333_335], array_column($rows, 'capital_cents'));
        $this->assertSame(2_000_000, array_sum(array_column($rows, 'capital_cents')));
    }

    public function test_a_conventional_campaign_uses_its_yearly_rate(): void
    {
        $campaign = $this->campaign(Campaign::CONTRACT_CONVENTIONAL, ['amount_requested' => '10000.00', 'interest_rate_percent' => '12.00', 'tenure_months' => 12]);

        $rows = ScheduleGenerator::forCampaign($campaign, Carbon::parse('2026-11-26 10:00', Campaign::LOCAL_TIMEZONE));

        $this->assertSame([1, 12], [$rows[0]['instalment_no'], $rows[11]['instalment_no']]);
        $this->assertSame(10_000, $rows[0]['return_cents']);
        $this->assertSame(1_000_000, array_sum(array_column($rows, 'capital_cents')));
    }

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function payoutDates(): array
    {
        return [
            'one month after the payout' => ['2026-11-26 10:00', ['2026-12-26', '2027-01-26', '2027-02-26']],
            'the 31st becomes the last day of a shorter month' => ['2027-01-31 09:00', ['2027-02-28', '2027-03-31', '2027-04-30']],
            'each date counts from the payout, not the month before' => ['2027-08-31 09:00', ['2027-09-30', '2027-10-31', '2027-11-30']],
            'a leap year' => ['2028-01-30 09:00', ['2028-02-29', '2028-03-30', '2028-04-30']],
        ];
    }

    #[DataProvider('payoutDates')]
    public function test_due_dates_are_monthly_from_the_payout_date(string $paidOutInMale, array $expected): void
    {
        $this->assertSame($expected, ScheduleGenerator::dueDates(Carbon::parse($paidOutInMale, Campaign::LOCAL_TIMEZONE), 3));
    }

    public function test_due_dates_follow_the_payout_date_in_the_maldives_not_utc(): void
    {
        // 20:00 UTC on 30 Nov is already 1 Dec in Malé (UTC+5).
        $this->assertSame(['2027-01-01'], ScheduleGenerator::dueDates(Carbon::parse('2026-11-30 20:00', 'UTC'), 1));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function campaign(string $contract, array $attributes): Campaign
    {
        return (new Campaign)->forceFill(['contract_type' => $contract, ...$attributes]);
    }
}
