<?php

namespace App\Support;

use App\Models\Campaign;
use App\Models\RepaymentSchedule;
use App\Models\User;
use App\Services\RepaymentService;
use App\Services\WalletLedger;

/**
 * What the borrower's repayment panel needs (PB-35), conventional and Murabahah only: the
 * wallet balance, the next instalment, and the payoff amount with how it is worked out. Every
 * sentence is here so the Shariah wording tests can read it: a Murabahah never says "interest".
 */
final class RepaymentPresenter
{
    public const CONVENTIONAL_PAYOFF = 'Paying off now settles the remaining capital, plus the interest of any instalment already due. Interest for periods not reached yet isn’t charged. There is no fee for paying early.';

    public const MURABAHAH_PAYOFF = 'The sale price was fixed when the contract was agreed, so paying off now settles the remaining sale price in full: the rest of the asset cost and of the markup. There is no fee for paying early.';

    public const IN_ORDER = 'Instalments are paid in order, one at a time, from your wallet. You can also pay off everything that is left at once.';

    /**
     * @return array<string, mixed>|null
     */
    public static function forBorrower(Campaign $campaign, User $borrower): ?array
    {
        if ($campaign->isMudarabah() || ! in_array($campaign->status, [Campaign::STATUS_REPAYING, Campaign::STATUS_COMPLETED], true)) {
            return null;
        }

        $murabahah = $campaign->contract_type === Campaign::CONTRACT_MURABAHAH;
        $unpaid = RepaymentSchedule::where('campaign_id', $campaign->id)->where('status', '!=', 'paid')->orderBy('instalment_no')->get();
        $next = $unpaid->first();
        $quote = $unpaid->isEmpty() ? null : RepaymentService::quote($campaign, $unpaid);
        [$state, $stateLabel] = $next === null ? [null, null] : SchedulePresenter::state($campaign, $next->load(['repayments', 'businessReports']), Campaign::today());

        return [
            'balance' => Money::fromCents(app(WalletLedger::class)->balanceFor($borrower)),
            'completed' => $campaign->status === Campaign::STATUS_COMPLETED,
            'note' => self::IN_ORDER,
            'noFeesNote' => SchedulePresenter::NO_FEES_NOTE,
            'returnLabel' => $murabahah ? 'Markup' : 'Interest',
            'next' => $next === null ? null : [
                'id' => $next->id,
                'no' => $next->instalment_no,
                'label' => "Instalment {$next->instalment_no}",
                'dateLabel' => $next->due_date->format('j M Y'),
                'state' => $state,
                'stateLabel' => $stateLabel,
                'capital' => (string) $next->capital_due,
                'return' => (string) $next->return_due,
                'amount' => Money::fromCents(Money::fromDatabase($next->capital_due) + Money::fromDatabase($next->return_due)),
            ],
            'payUrl' => route('borrower.campaigns.repayments.store', $campaign),
            'payoff' => $quote === null ? null : [
                'url' => route('borrower.campaigns.payoff', $campaign),
                'total' => Money::fromCents($quote['total']),
                'count' => count($quote['rows']),
                'explanation' => $murabahah ? self::MURABAHAH_PAYOFF : self::CONVENTIONAL_PAYOFF,
                'rows' => $murabahah
                    ? [
                        ['Instalments settled', (string) count($quote['rows'])],
                        ['Remaining asset cost', Money::format($quote['capital'])],
                        ['Remaining markup', Money::format($quote['return'])],
                    ]
                    : [
                        ['Instalments settled', (string) count($quote['rows'])],
                        ['Remaining capital', Money::format($quote['capital'])],
                        ['Interest on instalments already due', Money::format($quote['return'])],
                        ['Interest not charged', Money::format($quote['waived'])],
                    ],
            ],
        ];
    }
}
