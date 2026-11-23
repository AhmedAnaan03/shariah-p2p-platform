<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\KycDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Submits a borrower's financing request (PB-13) with a snapshot of their financial profile
 * (PB-14, from the profile since PB-58) and optional credit report, all or nothing.
 */
class CampaignSubmissionService
{
    public function __construct(private readonly KycDocumentStore $documents) {}

    /**
     * @param  array<string, mixed>  $data  StoreCampaignRequest::validated()
     */
    public function submit(User $borrower, array $data, ?UploadedFile $creditReport = null): Campaign
    {
        // The file is written to disk inside the transaction; if anything after it fails, the
        // rows roll back and this deletes the file, so nothing is left half-saved.
        $stored = null;

        try {
            return DB::transaction(function () use ($borrower, $data, $creditReport, &$stored) {
                $campaign = new Campaign($data);
                // Workflow fields are not mass-assignable: set them one by one.
                $campaign->borrower()->associate($borrower);
                $campaign->status = Campaign::STATUS_SUBMITTED;
                $campaign->submitted_at = now();
                $campaign->save();

                // PB-58: a snapshot of the verified financial profile, so this request is scored
                // on what was true when it was made, even if the profile changes later.
                $campaign->financials()->create($borrower->borrowerProfile->financialProfile());

                if ($creditReport !== null) {
                    $stored = $this->documents->store($borrower, $creditReport, KycDocument::TYPE_CREDIT_REPORT, $campaign);
                }

                AuditLog::record('campaign.submitted', $campaign, [
                    'contract_type' => $campaign->contract_type,
                    'amount_requested' => $campaign->amount_requested,
                    'tenure_months' => $campaign->tenure_months,
                    'credit_report_id' => $stored?->id,
                ], $borrower);

                return $campaign;
            });
        } catch (Throwable $e) {
            if ($stored !== null) {
                $this->documents->discard($stored);
            }

            throw $e;
        }
    }
}
