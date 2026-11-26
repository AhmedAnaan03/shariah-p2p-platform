<?php

namespace App\Support;

use App\Models\Campaign;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Works out a funded campaign's repayment schedule (PB-34, PB-28): one row a month, the first a
 * month after the money was paid out. All the arithmetic is in whole cents, so nothing is lost
 * to floating-point rounding, and every schedule adds up exactly.
 *
 * - Conventional: equal monthly instalments of capital plus interest on the balance still owed
 *   (an amortised loan). The last instalment repays exactly what is left, so the capital adds up
 *   to the amount to the cent.
 * - Murabahah: (asset cost + agreed markup) ÷ tenure. The sale price is split into equal
 *   instalments, rounded down to the cent, with the cents left over on the last one, so the total
 *   is exactly the sale price that was disclosed. Each instalment is then divided into markup and
 *   capital in proportion (InstalmentSplit), so the markup adds up to the agreed markup and the
 *   capital to the asset cost, both exactly.
 * - Mudarabah: a planned capital return schedule, not a debt. Capital ÷ tenure, split the same
 *   way, and no return figure at all (return NULL): investors' returns come from the profit the
 *   business reports (PB-29), so a fixed figure would be a guaranteed return.
 *
 * Plain functions with no database access, so every case is unit-tested on its own.
 */
final class ScheduleGenerator
{
    /**
     * The rows for a campaign paid out at $disbursedAt.
     *
     * @return list<array{instalment_no: int, due_date: string, capital_cents: int, return_cents: int|null}>
     */
    public static function forCampaign(Campaign $campaign, DateTimeInterface $disbursedAt): array
    {
        $months = (int) $campaign->tenure_months;
        $amount = Money::fromDatabase($campaign->amount_requested);

        [$capital, $returns] = match ($campaign->contract_type) {
            Campaign::CONTRACT_CONVENTIONAL => self::amortised($amount, (float) $campaign->interest_rate_percent, $months),
            Campaign::CONTRACT_MURABAHAH => self::murabahah($amount, Money::fromDatabase($campaign->markup_amount), $months),
            Campaign::CONTRACT_MUDARABAH => [self::evenSplit($amount, $months), array_fill(0, $months, null)],
        };

        $rows = [];
        foreach (self::dueDates($disbursedAt, $months) as $i => $date) {
            $rows[] = [
                'instalment_no' => $i + 1,
                'due_date' => $date,
                'capital_cents' => $capital[$i],
                'return_cents' => $returns[$i],
            ];
        }

        return $rows;
    }

    /**
     * $total cents in $parts equal parts, rounded down to the cent, with the cents left over on
     * the last part: 1,000,000 in 3 → 333,333 + 333,333 + 333,334. Always adds up to $total.
     *
     * @return list<int>
     */
    public static function evenSplit(int $total, int $parts): array
    {
        self::assertValid($total, $parts);
        $each = intdiv($total, $parts);
        $split = array_fill(0, $parts, $each);
        $split[$parts - 1] = $total - $each * ($parts - 1);

        return $split;
    }

    /**
     * An amortised loan of $principal cents at $yearlyRatePercent over $months, as capital and
     * interest per month.
     *
     * The monthly payment is P = A·r / (1 − (1 + r)^−n) with r = the yearly rate ÷ 12, rounded to
     * the cent. Each month's interest is the balance still owed × r, rounded to the cent, and the
     * rest of the payment repays capital. The last month repays exactly the balance left (so it
     * can differ from the others by a few cents), which makes the capital add up to $principal.
     * At 0% it is simply the principal split evenly.
     *
     * @return array{0: list<int>, 1: list<int>} [capital per month, interest per month]
     */
    public static function amortised(int $principal, float $yearlyRatePercent, int $months): array
    {
        self::assertValid($principal, $months);
        if ($yearlyRatePercent < 0) {
            throw new InvalidArgumentException('The rate cannot be negative.');
        }

        $r = $yearlyRatePercent / 100 / 12;
        if ($r == 0.0) {
            return [self::evenSplit($principal, $months), array_fill(0, $months, 0)];
        }

        $payment = (int) round($principal * $r / (1 - (1 + $r) ** -$months));
        $balance = $principal;
        $capital = $interest = [];
        for ($month = 1; $month <= $months; $month++) {
            $monthInterest = (int) round($balance * $r);
            $monthCapital = $month === $months ? $balance : min($balance, $payment - $monthInterest);
            $capital[] = $monthCapital;
            $interest[] = $monthInterest;
            $balance -= $monthCapital;
        }

        return [$capital, $interest];
    }

    /**
     * A Murabahah sale price ($cost + $markup cents) in $months equal instalments, each divided
     * into capital and markup in proportion: MVR 50,000 + MVR 4,000 over 12 months is twelve
     * instalments of exactly MVR 4,500.00.
     *
     * @return array{0: list<int>, 1: list<int>} [capital per month, markup per month]
     */
    public static function murabahah(int $cost, int $markup, int $months): array
    {
        self::assertValid($markup, $months);
        $instalments = self::evenSplit($cost + $markup, $months);
        if ($cost + $markup === 0) {
            return [$instalments, $instalments];
        }

        // Each instalment's share of the markup, in proportion to its size, adding up exactly.
        $markups = array_values(InstalmentSplit::shares($markup, $instalments));
        $capital = array_map(fn (int $instalment, int $part) => $instalment - $part, $instalments, $markups);

        return [$capital, $markups];
    }

    /**
     * The due dates ("2026-12-26"): month 1, 2, … after the payout's date in the Maldives. Each is
     * counted from the payout date itself, not from the month before, and a day the month doesn't
     * have becomes its last day: paid out on 31 Jan → 28 Feb, 31 Mar, 30 Apr …
     *
     * @return list<string>
     */
    public static function dueDates(DateTimeInterface $disbursedAt, int $months): array
    {
        $start = Carbon::instance($disbursedAt)->setTimezone(Campaign::LOCAL_TIMEZONE)->startOfDay();

        return array_map(fn (int $month) => $start->copy()->addMonthsNoOverflow($month)->toDateString(), range(1, $months));
    }

    private static function assertValid(int $total, int $parts): void
    {
        if ($parts < 1) {
            throw new InvalidArgumentException('A schedule needs at least one month.');
        }
        if ($total < 0) {
            throw new InvalidArgumentException('An amount cannot be negative.');
        }
    }
}
