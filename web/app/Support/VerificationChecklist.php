<?php

namespace App\Support;

use App\Models\KycDocument;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * What a borrower or investor still has to provide before an administrator can review them
 * (PB-09, PB-10, PB-11; PB-53 added the address, the document slots, the investor's risk
 * acknowledgement and the explicit submission). Used by the onboarding wizard, the portal
 * checklists and the admin queue, so the user and the admin always agree on what "ready for
 * review" means.
 *
 * Expects the user's role, profile and kycDocuments to be loaded (or loads them lazily).
 */
final class VerificationChecklist
{
    private function __construct(private readonly User $user) {}

    public static function for(User $user): self
    {
        return new self($user);
    }

    public function profile(): ?Model
    {
        return match ($this->user->role->name) {
            Role::BORROWER => $this->user->borrowerProfile,
            Role::INVESTOR => $this->user->investorProfile,
            default => null,
        };
    }

    public function hasProfile(): bool
    {
        return $this->profile() !== null;
    }

    /**
     * Every address field filled in and, for an address moved from the old single line,
     * checked by the user.
     */
    public function hasCompleteAddress(): bool
    {
        return $this->profile()?->hasCompleteAddress() ?? false;
    }

    /**
     * A borrower's financial profile (PB-58): every field the risk model needs. Investors have
     * none.
     */
    public function hasFinancialProfile(): bool
    {
        if (! $this->user->hasRole(Role::BORROWER)) {
            return true;
        }

        return $this->user->borrowerProfile?->hasFinancialProfile() ?? false;
    }

    /**
     * The user's document slots (DocumentSlots), every one of them required.
     *
     * @return list<string>
     */
    public function requiredDocumentTypes(): array
    {
        return DocumentSlots::typesFor($this->user);
    }

    /**
     * Required types with no current upload, or whose current upload was rejected (it has to
     * be replaced).
     *
     * @return list<string>
     */
    public function missingDocumentTypes(): array
    {
        $provided = $this->user->kycDocuments
            ->filter(fn (KycDocument $document) => $document->counts())
            ->pluck('document_type')
            ->all();

        return array_values(array_diff($this->requiredDocumentTypes(), $provided));
    }

    public function hasAllDocuments(): bool
    {
        return $this->hasProfile() && $this->missingDocumentTypes() === [];
    }

    /**
     * Investors must accept the risk acknowledgement as their last step; borrowers have no
     * such step.
     */
    public function hasAcceptedRisk(): bool
    {
        if (! $this->user->hasRole(Role::INVESTOR)) {
            return true;
        }

        return $this->user->investorProfile?->risk_acknowledged_at !== null;
    }

    /**
     * Everything provided: what the user needs before they can submit, and what an admin
     * needs before they can approve.
     */
    public function isComplete(): bool
    {
        return $this->hasProfile() && $this->hasCompleteAddress() && $this->hasFinancialProfile()
            && $this->hasAllDocuments() && $this->hasAcceptedRisk();
    }

    public function isSubmitted(): bool
    {
        return $this->user->verification_submitted_at !== null;
    }

    /**
     * Complete, submitted and waiting for an administrator: what puts a user in the admin queue.
     */
    public function isReadyForReview(): bool
    {
        return $this->user->isPending() && $this->isComplete() && $this->isSubmitted();
    }

    /**
     * For the React checklists.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $missing = $this->missingDocumentTypes();

        return [
            'has_profile' => $this->hasProfile(),
            'has_address' => $this->hasCompleteAddress(),
            'has_finances' => $this->hasFinancialProfile(),
            'required_documents' => array_map(fn (string $type) => [
                'type' => $type,
                'label' => DocumentSlots::labelFor($this->user, $type),
                'provided' => ! in_array($type, $missing, true),
            ], $this->requiredDocumentTypes()),
            'complete' => $this->isComplete(),
            'submitted' => $this->isSubmitted(),
        ];
    }
}
