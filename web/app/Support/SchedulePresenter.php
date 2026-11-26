<?php

namespace App\Support;

use App\Models\Campaign;
use App\Models\Pledge;
use App\Models\RepaymentSchedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A funded campaign's schedule as its pages and emails show it (PB-34, PB-28), with every label
 * in one place so the wording rules can be tested.
 *
 * Two different things share the repayment_schedules table:
 *
 * - Conventional and Murabahah: instalments the borrower owes. An unpaid one past its due date
 *   is late.
 * - Mudarabah: a planned capital return schedule, not a debt. It is never called an instalment,
 *   "due" or "owed", and a passed date is never "late": it is "planned date passed, awaiting
 *   business report" until PB-29 defines what the business reports and what follows.
 *
 * Dates are Maldives dates, like every other date shown.
 */
final class SchedulePresenter
{
    /** Paid (PB-35 records payments). */
    public const STATE_PAID = 'paid';

    /** Due today (an instalment) or planned for today (a capital return). */
    public const STATE_DUE = 'due';

    /** Its date is still ahead. */
    public const STATE_UPCOMING = 'upcoming';

    /** An instalment past its due date and not paid. Never used for a Mudarabah. */
    public const STATE_LATE = 'late';

    /** A Mudarabah planned capital return whose date has passed, waiting for the business's report (PB-29). */
    public const STATE_AWAITING = 'awaiting';

    public const AWAITING_LABEL = 'Planned date passed, awaiting business report';

    /**
     * What borrowers and investors are told about a Mudarabah's capital (PB-34 review), on its
     * pages and in its emails. Loss allocation and negligence or misconduct are PB-29's.
     */
    public const MUDARABAH_CAPITAL_NOTE = 'Capital is returned according to the business’s results. '
        .'In a genuine business loss, investors bear the loss of capital in proportion to their share, '
        .'except in cases of negligence or misconduct. Profit is shared at the agreed ratio once the business reports it.';

    /**
     * The words for one campaign's schedule.
     *
     * @return array{kind: string, title: string, row: string, rows: string, date: string, capital: string, return: string|null, amount: string, chartTitle: string}
     */
    public static function labels(Campaign $campaign): array
    {
        if ($campaign->isMudarabah()) {
            return [
                'kind' => 'capital_returns',
                'title' => 'Planned capital return schedule',
                'row' => 'Planned capital return',
                'rows' => 'Planned capital returns',
                'date' => 'Planned date',
                'capital' => 'Planned capital return',
                'return' => null,
                'amount' => 'Planned capital return',
                'chartTitle' => 'Planned capital returns by month',
            ];
        }

        return [
            'kind' => 'instalments',
            'title' => 'Repayment schedule',
            'row' => 'Instalment',
            'rows' => 'Instalments',
            'date' => 'Due date',
            'capital' => 'Capital',
            'return' => $campaign->contract_type === Campaign::CONTRACT_MURABAHAH ? 'Markup' : 'Interest',
            'amount' => 'Instalment',
            'chartTitle' => 'Capital and '.($campaign->contract_type === Campaign::CONTRACT_MURABAHAH ? 'markup' : 'interest').' by month',
        ];
    }

    /**
     * The schedule for the borrower's request page; null before it is paid out.
     *
     * @return array<string, mixed>|null
     */
    public static function forBorrower(Campaign $campaign): ?array
    {
        $schedules = self::schedules($campaign);
        if ($schedules->isEmpty()) {
            return null;
        }

        return [
            ...self::summary($campaign, $schedules),
            'note' => self::borrowerNote($campaign),
            'rows' => self::rows($campaign, $schedules),
        ];
    }

    /**
     * The schedule for an investor's campaign page, with their share of every row; null before
     * it is paid out or if they have no settled pledge in it.
     *
     * @return array<string, mixed>|null
     */
    public static function forInvestor(Campaign $campaign, User $investor): ?array
    {
        $schedules = self::schedules($campaign);
        $weights = self::weights($campaign);
        if ($schedules->isEmpty() || ! isset($weights[$investor->id])) {
            return null;
        }

        $rows = self::rows($campaign, $schedules, $weights, $investor->id);
        $shareTotal = array_sum(array_map(fn (array $row) => Money::toCents($row['share']), $rows));

        return [
            ...self::summary($campaign, $schedules),
            'note' => self::investorNote($campaign),
            'yourPledged' => Money::fromCents($weights[$investor->id]),
            'yourShareTotal' => Money::fromCents($shareTotal),
            'yourSharePercent' => round($weights[$investor->id] / array_sum($weights) * 100, 1),
            'rows' => $rows,
        ];
    }

    /**
     * The first row, for the payout emails: its date and amount, and each investor's share of it.
     *
     * @return array{dateLabel: string, cents: int, shares: array<int, int>}|null
     */
    public static function first(Campaign $campaign): ?array
    {
        $first = self::schedules($campaign)->first();
        if ($first === null) {
            return null;
        }
        $weights = self::weights($campaign);
        $capital = Money::fromDatabase($first->capital_due);
        $return = $first->return_due === null ? 0 : Money::fromDatabase($first->return_due);

        $shares = [];
        if ($weights !== []) {
            $capitalShares = InstalmentSplit::shares($capital, $weights);
            $returnShares = InstalmentSplit::shares($return, $weights);
            foreach (array_keys($weights) as $id) {
                $shares[$id] = $capitalShares[$id] + $returnShares[$id];
            }
        }

        return ['dateLabel' => self::dateLabel($first->due_date), 'cents' => $capital + $return, 'shares' => $shares];
    }

    public static function borrowerNote(Campaign $campaign): string
    {
        $n = (int) $campaign->tenure_months;

        return match ($campaign->contract_type) {
            Campaign::CONTRACT_CONVENTIONAL => "You repay {$n} monthly instalments of capital and interest, the first one month after the payout. Each month's interest is on the capital still owed, so it falls as you repay; the last instalment can differ by a few laari.",
            Campaign::CONTRACT_MURABAHAH => "You pay the sale price, the asset cost plus the fixed markup, in {$n} equal monthly instalments, the first one month after the payout. The last can differ by a few laari. The markup was agreed before funding and can't change.",
            Campaign::CONTRACT_MUDARABAH => "The plan returns the investors' capital in {$n} monthly amounts, the first one month after the payout. ".self::MUDARABAH_CAPITAL_NOTE,
        };
    }

    public static function investorNote(Campaign $campaign): string
    {
        $n = (int) $campaign->tenure_months;

        return match ($campaign->contract_type) {
            Campaign::CONTRACT_CONVENTIONAL => "The borrower repays in {$n} monthly instalments of capital and interest. Your share of each is in proportion to your pledge. You could lose some or all of your money if the borrower doesn't repay.",
            Campaign::CONTRACT_MURABAHAH => "The business pays the sale price, the asset cost plus the fixed markup, in {$n} monthly instalments. Your share of each is in proportion to your pledge. The markup is fixed, but your returns could be lower than expected, or you could receive nothing, if the business doesn't pay.",
            Campaign::CONTRACT_MUDARABAH => "The plan returns capital in {$n} monthly amounts; your share of each is in proportion to your pledge. ".self::MUDARABAH_CAPITAL_NOTE,
        };
    }

    /**
     * @return Collection<int, RepaymentSchedule>
     */
    private static function schedules(Campaign $campaign): Collection
    {
        return $campaign->repaymentSchedules()->orderBy('instalment_no')->get();
    }

    /**
     * Each investor's settled pledges in cents: what their share of every row is in proportion to.
     *
     * @return array<int, int>
     */
    private static function weights(Campaign $campaign): array
    {
        return Pledge::where('campaign_id', $campaign->id)->where('status', 'settled')
            ->selectRaw('investor_id, SUM(amount) as total')->groupBy('investor_id')->orderBy('investor_id')
            ->pluck('total', 'investor_id')
            ->map(fn ($total) => Money::fromDatabase($total))
            ->all();
    }

    /**
     * @param  Collection<int, RepaymentSchedule>  $schedules
     * @return array<string, mixed>
     */
    private static function summary(Campaign $campaign, Collection $schedules): array
    {
        $capital = $schedules->sum(fn (RepaymentSchedule $s) => Money::fromDatabase($s->capital_due));
        $return = $campaign->isMudarabah() ? null : $schedules->sum(fn (RepaymentSchedule $s) => Money::fromDatabase($s->return_due));
        $first = $schedules->first();

        return [
            'labels' => self::labels($campaign),
            'count' => $schedules->count(),
            'capitalTotal' => Money::fromCents($capital),
            'returnTotal' => $return === null ? null : Money::fromCents($return),
            'total' => Money::fromCents($capital + ($return ?? 0)),
            'firstDateLabel' => self::dateLabel($first->due_date),
            'disbursedOnLabel' => $campaign->disbursed_at ? Campaign::localDate($campaign->disbursed_at) : null,
        ];
    }

    /**
     * One entry per row. With $weights and $investorId, each also carries that investor's share.
     *
     * @param  Collection<int, RepaymentSchedule>  $schedules
     * @param  array<int, int>  $weights
     * @return list<array<string, mixed>>
     */
    private static function rows(Campaign $campaign, Collection $schedules, array $weights = [], ?int $investorId = null): array
    {
        $labels = self::labels($campaign);
        $today = now()->setTimezone(Campaign::LOCAL_TIMEZONE)->toDateString();

        return $schedules->map(function (RepaymentSchedule $schedule) use ($campaign, $labels, $today, $weights, $investorId) {
            $capital = Money::fromDatabase($schedule->capital_due);
            $return = $schedule->return_due === null ? null : Money::fromDatabase($schedule->return_due);
            [$state, $stateLabel] = self::state($campaign, $schedule, $today);

            $row = [
                'id' => $schedule->id,
                'no' => $schedule->instalment_no,
                'label' => "{$labels['row']} {$schedule->instalment_no}",
                'date' => $schedule->due_date->toDateString(),
                'dateLabel' => self::dateLabel($schedule->due_date),
                'monthLabel' => $schedule->due_date->format('M Y'),
                'capital' => Money::fromCents($capital),
                'return' => $return === null ? null : Money::fromCents($return),
                'amount' => Money::fromCents($capital + ($return ?? 0)),
                'state' => $state,
                'stateLabel' => $stateLabel,
            ];

            if ($investorId !== null) {
                $share = InstalmentSplit::shares($capital, $weights)[$investorId]
                    + ($return === null ? 0 : InstalmentSplit::shares($return, $weights)[$investorId]);
                $row['share'] = Money::fromCents($share);
            }

            return $row;
        })->values()->all();
    }

    /**
     * Where one row stands today, and how it is worded.
     *
     * @return array{0: string, 1: string}
     */
    public static function state(Campaign $campaign, RepaymentSchedule $schedule, string $today): array
    {
        $mudarabah = $campaign->isMudarabah();
        $date = $schedule->due_date->toDateString();

        if ($schedule->status === 'paid') {
            return [self::STATE_PAID, $mudarabah ? 'Capital returned' : 'Paid'];
        }
        if ($date > $today) {
            return [self::STATE_UPCOMING, ($mudarabah ? 'Planned for ' : 'Due ').self::dateLabel($schedule->due_date)];
        }
        if ($date === $today) {
            return [self::STATE_DUE, $mudarabah ? 'Planned for today' : 'Due today'];
        }
        if ($mudarabah) {
            return [self::STATE_AWAITING, self::AWAITING_LABEL];
        }
        $days = (int) Carbon::parse($date)->diffInDays(Carbon::parse($today));

        return [self::STATE_LATE, $days === 1 ? '1 day late' : "{$days} days late"];
    }

    private static function dateLabel(Carbon $date): string
    {
        return $date->format('j M Y');
    }
}
