<?php

namespace App\Services;

use App\Models\BusinessReport;
use App\Models\Campaign;
use App\Models\InvestorPayout;
use App\Models\RepaymentSchedule;
use App\Models\User;
use App\Notifications\BusinessReportDecided;
use App\Notifications\CampaignCompleted;
use App\Support\CampaignInvestors;
use App\Support\Money;
use App\Support\MudarabahCapital;

/**
 * Tells the borrower and each investor, after the money has moved, that a campaign is finished
 * (PB-36, PB-29) or that a Mudarabah report was decided (PB-29), each with their own figures.
 */
class RepaymentNotifier
{
    public function completed(Campaign $campaign): void
    {
        if ($campaign->isMudarabah()) {
            $accounts = MudarabahCapital::accounts($campaign);
            $totals = MudarabahCapital::totals($accounts);
            $campaign->borrower->notify(new CampaignCompleted($campaign, CampaignCompleted::BORROWER, $totals));
            foreach (User::whereKey(array_keys($accounts))->get() as $investor) {
                $investor->notify(new CampaignCompleted($campaign, CampaignCompleted::INVESTOR, $accounts[$investor->id]));
            }

            return;
        }

        $payouts = InvestorPayout::where('campaign_id', $campaign->id)
            ->selectRaw('investor_id, SUM(capital_amount) as capital, SUM(return_amount) as ret')
            ->groupBy('investor_id')->get()->keyBy('investor_id');
        $scheduled = RepaymentSchedule::where('campaign_id', $campaign->id)->sum('return_due');
        $charged = $campaign->repaymentSchedules()->join('repayments', 'repayments.repayment_schedule_id', '=', 'repayment_schedules.id')
            ->selectRaw('SUM(repayments.capital_amount) as capital, SUM(repayments.return_amount) as ret')->first();
        $capital = Money::fromDatabase($charged?->capital);
        $return = Money::fromDatabase($charged?->ret);

        $campaign->borrower->notify(new CampaignCompleted($campaign, CampaignCompleted::BORROWER, [
            'capital' => $capital,
            'return' => $return,
            'total' => $capital + $return,
            'waived' => Money::fromDatabase($scheduled) - $return,
        ]));
        foreach (User::whereKey(array_keys(CampaignInvestors::weights($campaign)))->get() as $investor) {
            $row = $payouts[$investor->id] ?? null;
            $investorCapital = Money::fromDatabase($row?->capital);
            $investorReturn = Money::fromDatabase($row?->ret);
            $investor->notify(new CampaignCompleted($campaign, CampaignCompleted::INVESTOR, [
                'capital' => $investorCapital,
                'return' => $investorReturn,
                'total' => $investorCapital + $investorReturn,
            ]));
        }
    }

    public function reportDecided(BusinessReport $report): void
    {
        $report->loadMissing(['campaign.borrower', 'repaymentSchedule']);
        $report->campaign->borrower->notify(new BusinessReportDecided($report, BusinessReportDecided::BORROWER));

        if ($report->status !== BusinessReport::STATUS_APPROVED) {
            return;
        }

        $parts = $report->payouts()->get()->keyBy('investor_id');
        foreach (User::whereKey(array_keys(CampaignInvestors::weights($report->campaign)))->get() as $investor) {
            $row = $parts[$investor->id] ?? null;
            $investor->notify(new BusinessReportDecided($report, BusinessReportDecided::INVESTOR, [
                'capital' => Money::fromDatabase($row?->capital_amount),
                'restored' => Money::fromDatabase($row?->loss_restored_amount),
                'profit' => Money::fromDatabase($row?->return_amount),
                'loss' => Money::fromDatabase($row?->loss_amount),
            ]));
        }
    }
}
