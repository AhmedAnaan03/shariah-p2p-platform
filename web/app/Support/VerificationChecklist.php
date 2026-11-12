<?php

namespace App\Support;

use App\Models\KycDocument;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * What a borrower or investor still has to provide before an administrator can review them
 * (PB-09, PB-10, PB-11). Used by the portal checklists and to build the admin queue, so the
 * user and the admin always agree on what "ready for review" means.
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
     * Documents everyone must provide: a national ID; an SME borrower also needs its business
     * registration certificate.
     *
     * @return list<string>
     */
    public function requiredDocumentTypes(): array
    {
        $required = ['national_id'];

        if ($this->user->hasRole(Role::BORROWER) && $this->user->borrowerProfile?->isSme()) {
            $required[] = 'business_registration';
        }

        return $required;
    }

    /**
     * Required types with no pending or approved upload. A rejected upload doesn't count.
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

    public function isComplete(): bool
    {
        return $this->hasProfile() && $this->missingDocumentTypes() === [];
    }

    /**
     * Complete, and waiting for an administrator: what puts a user in the admin queue.
     */
    public function isReadyForReview(): bool
    {
        return $this->user->isPending() && $this->isComplete();
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
            'required_documents' => array_map(fn (string $type) => [
                'type' => $type,
                'label' => KycDocument::TYPE_LABELS[$type],
                'provided' => ! in_array($type, $missing, true),
            ], $this->requiredDocumentTypes()),
            'complete' => $this->isComplete(),
        ];
    }
}
