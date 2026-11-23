<?php

namespace App\Support;

use App\Models\KycDocument;
use App\Models\Role;
use App\Models\User;

/**
 * The fixed document slots each kind of user fills in the onboarding wizard (PB-53). Every
 * slot is required and holds one current document; uploading again replaces it. A type that
 * isn't one of the user's slots can't be uploaded at all.
 *
 *   individual borrower  national ID, proof of address, salary slip (PB-58)
 *   SME borrower         national ID, business registration, proof of address, financial statement
 *   investor             national ID, proof of address
 *
 * One place, so the wizard, the upload validation, the checklist and the admin review always
 * agree on what is needed.
 */
final class DocumentSlots
{
    public const INDIVIDUAL = 'individual';

    public const SME = 'sme';

    public const INVESTOR = 'investor';

    /**
     * What each slot asks for, per kind of user. The label names the document; the
     * description says exactly what to upload.
     *
     * @var array<string, array<string, array{label: string, description: string}>>
     */
    private const SLOTS = [
        self::INDIVIDUAL => [
            'national_id' => ['label' => 'National ID card', 'description' => 'Both sides of your Maldivian national ID card, in one PDF or as one photo.'],
            'proof_of_address' => ['label' => 'Proof of address', 'description' => 'A utility bill, tenancy agreement or island council letter from the last 3 months, showing your name and address.'],
            // PB-58: lets the administrator check the monthly income in the financial profile.
            'salary_slip' => ['label' => 'Salary slip', 'description' => 'Your salary slips for the last 3 months, showing your monthly income before tax. One PDF can contain several months.'],
        ],
        self::SME => [
            'national_id' => ['label' => 'National ID card', 'description' => 'Both sides of the national ID card of the business owner or a director.'],
            'business_registration' => ['label' => 'Business registration certificate', 'description' => 'The certificate from the Ministry of Economic Development, showing the registration number on your profile.'],
            'proof_of_address' => ['label' => 'Proof of business address', 'description' => 'A utility bill, tenancy agreement or council letter from the last 3 months for the business premises.'],
            'financial_statement' => ['label' => 'Financial statement', 'description' => 'Your most recent annual accounts or 6 months of business bank statements.'],
        ],
        self::INVESTOR => [
            'national_id' => ['label' => 'National ID card', 'description' => 'Both sides of your Maldivian national ID card, in one PDF or as one photo.'],
            'proof_of_address' => ['label' => 'Proof of address', 'description' => 'A utility bill, tenancy agreement or island council letter from the last 3 months, showing your name and address.'],
        ],
    ];

    /**
     * Which set of slots applies: a borrower's depends on the profile's borrower type (an
     * individual until the profile says SME).
     */
    public static function kindFor(User $user): ?string
    {
        return match ($user->role?->name) {
            Role::INVESTOR => self::INVESTOR,
            Role::BORROWER => $user->borrowerProfile?->isSme() ? self::SME : self::INDIVIDUAL,
            default => null,
        };
    }

    /**
     * The document types the user must provide, in display order.
     *
     * @return list<string>
     */
    public static function typesFor(User $user): array
    {
        return array_keys(self::SLOTS[self::kindFor($user)] ?? []);
    }

    /**
     * The slots with what each asks for and the document currently in it (or null), for the
     * documents step. Expects the user's current kycDocuments.
     *
     * @return list<array<string, mixed>>
     */
    public static function forPage(User $user): array
    {
        $current = $user->kycDocuments->keyBy('document_type');

        return array_map(fn (string $type) => [
            'type' => $type,
            ...self::SLOTS[self::kindFor($user)][$type],
            'document' => $current->get($type)?->toListItem(),
        ], self::typesFor($user));
    }

    /**
     * The label of a slot for this user, falling back to the plain document type label.
     */
    public static function labelFor(User $user, string $type): string
    {
        return self::SLOTS[self::kindFor($user)][$type]['label'] ?? KycDocument::TYPE_LABELS[$type];
    }
}
