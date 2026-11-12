<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\KycDocument;
use App\Models\User;
use App\Notifications\VerificationDecided;
use App\Support\VerificationChecklist;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An administrator's verification decision on a borrower or investor (PB-11).
 *
 * One decision covers the account and every document still waiting for review. Everything
 * the decision changes is written in one database transaction, so a failure part-way leaves
 * nothing half-done; the user is notified only after the transaction has committed.
 */
class VerificationService
{
    public function approve(User $applicant, User $admin): User
    {
        return $this->decide($applicant, $admin, approved: true, reason: null);
    }

    public function reject(User $applicant, User $admin, string $reason): User
    {
        return $this->decide($applicant, $admin, approved: false, reason: $reason);
    }

    private function decide(User $applicant, User $admin, bool $approved, ?string $reason): User
    {
        $applicant = DB::transaction(function () use ($applicant, $admin, $approved, $reason) {
            // Lock the user's row until the transaction ends: if two admins decide at the same
            // moment, the second waits, then sees the account is no longer pending.
            $applicant = User::whereKey($applicant->id)->lockForUpdate()->firstOrFail();

            if (! $applicant->isPending()) {
                throw ValidationException::withMessages([
                    'decision' => 'This account has already been decided, so it is no longer waiting for review.',
                ]);
            }

            $checklist = VerificationChecklist::for($applicant);
            if ($approved && ! $checklist->isComplete()) {
                throw ValidationException::withMessages([
                    'decision' => 'This account can\'t be approved yet: its profile or required documents are missing.',
                ]);
            }

            $statusBefore = $applicant->status;
            $documentIds = $applicant->kycDocuments()->where('status', 'pending')->pluck('id')->all();

            // status and status_reason are not mass-assignable; set them one by one.
            $applicant->status = $approved ? 'verified' : 'rejected';
            $applicant->status_reason = $reason;
            $applicant->save();

            // Documents rejected in an earlier review keep that earlier decision.
            KycDocument::whereKey($documentIds)->update([
                'status' => $approved ? 'approved' : 'rejected',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            AuditLog::record($approved ? 'user.verified' : 'user.rejected', $applicant, [
                'reason' => $reason,
                'status_before' => $statusBefore,
                'status_after' => $applicant->status,
                'document_ids' => $documentIds,
            ], $admin);

            return $applicant;
        });

        // After the commit: a rolled-back decision never sends an email.
        $applicant->notify(new VerificationDecided($approved, $reason));

        return $applicant;
    }
}
