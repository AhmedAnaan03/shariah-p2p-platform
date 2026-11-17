<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\User;
use App\Notifications\CampaignDecided;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An administrator's decision on a financing campaign (PB-15):
 *
 *   approve   submitted -> live       opens it to investors until a funding deadline
 *   reject    submitted -> rejected   a reason is required; the borrower sees it
 *   suspend   live      -> suspended  a reason is required; it leaves the marketplace
 *
 * Built like VerificationService (PB-11): the campaign row is locked, everything the decision
 * changes (campaign, attached credit report, audit entry) is written in one transaction, and
 * the borrower is notified only after it has committed.
 */
class CampaignReviewService
{
    public const APPROVE = 'approve';

    public const REJECT = 'reject';

    public const SUSPEND = 'suspend';

    /**
     * Which status each decision starts from and leads to.
     */
    private const TRANSITIONS = [
        self::APPROVE => [Campaign::STATUS_SUBMITTED, Campaign::STATUS_LIVE],
        self::REJECT => [Campaign::STATUS_SUBMITTED, Campaign::STATUS_REJECTED],
        self::SUSPEND => [Campaign::STATUS_LIVE, Campaign::STATUS_SUSPENDED],
    ];

    /**
     * The audit log action for each decision.
     */
    private const AUDIT_ACTIONS = [
        self::APPROVE => 'campaign.approved',
        self::REJECT => 'campaign.rejected',
        self::SUSPEND => 'campaign.suspended',
    ];

    /**
     * The decisions an administrator can make on a campaign in its current status.
     *
     * @return list<string>
     */
    public static function decisionsFor(Campaign $campaign): array
    {
        return array_keys(array_filter(self::TRANSITIONS, fn (array $t) => $t[0] === $campaign->status));
    }

    public function approve(Campaign $campaign, User $admin): Campaign
    {
        return $this->decide($campaign, $admin, self::APPROVE, null);
    }

    public function reject(Campaign $campaign, User $admin, string $reason): Campaign
    {
        return $this->decide($campaign, $admin, self::REJECT, $reason);
    }

    public function suspend(Campaign $campaign, User $admin, string $reason): Campaign
    {
        return $this->decide($campaign, $admin, self::SUSPEND, $reason);
    }

    private function decide(Campaign $campaign, User $admin, string $decision, ?string $reason): Campaign
    {
        [$from, $to] = self::TRANSITIONS[$decision];

        $campaign = DB::transaction(function () use ($campaign, $admin, $decision, $reason, $from, $to) {
            // If two administrators decide at the same moment, the second waits here, then
            // sees the new status and is refused below.
            $campaign = Campaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();

            if ($campaign->status !== $from) {
                throw ValidationException::withMessages([
                    'decision' => self::refusal($decision, $campaign->status),
                ]);
            }

            $statusBefore = $campaign->status;
            $campaign->status = $to;
            $campaign->status_reason = $reason;

            if ($decision === self::APPROVE) {
                $campaign->approved_at = now();
                // The end of the 30th day in the Maldives, so "30 days" always means 30 full
                // days to pledge, whatever time of day it was approved.
                $campaign->funding_deadline = Campaign::fundingDeadlineFrom(now());
            }

            $campaign->save();

            // The credit report is checked as part of reviewing the request, so it takes the
            // request's outcome. A suspension happens after approval and leaves it as it was.
            $report = $campaign->creditReport()->where('status', 'pending')->first();
            if ($report !== null && $decision !== self::SUSPEND) {
                $report->status = $decision === self::APPROVE ? 'approved' : 'rejected';
                $report->reviewed_by = $admin->id;
                $report->reviewed_at = now();
                $report->rejection_reason = $reason;
                $report->save();
            }

            AuditLog::record(self::AUDIT_ACTIONS[$decision], $campaign, [
                'decision' => $decision,
                'reason' => $reason,
                'status_before' => $statusBefore,
                'status_after' => $to,
                'funding_deadline' => $campaign->funding_deadline?->toIso8601String(),
                'credit_report_id' => $report?->id,
            ], $admin);

            return $campaign;
        });

        $campaign->borrower->notify(new CampaignDecided($campaign, $decision, $reason));

        return $campaign;
    }

    private static function refusal(string $decision, string $status): string
    {
        return match ($decision) {
            self::SUSPEND => "Only a live campaign can be suspended. This one is {$status}.",
            default => "This request has already been decided, so it is no longer waiting for review. It is {$status}.",
        };
    }
}
