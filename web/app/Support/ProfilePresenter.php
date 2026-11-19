<?php

namespace App\Support;

use App\Models\BorrowerProfile;
use App\Models\User;

/**
 * A borrower's or investor's profile as label/value pairs (PB-11; shared with the wizard's
 * review step by PB-53), so the user reviews exactly what the administrator will check.
 *
 * Includes the decrypted national ID number: only the owner and administrators reach the pages
 * that call this.
 */
final class ProfilePresenter
{
    /**
     * @return list<array{label: string, value: mixed}>|null null if there is no profile yet.
     */
    public static function details(User $user): ?array
    {
        $profile = VerificationChecklist::for($user)->profile();

        if ($profile === null) {
            return null;
        }

        $profile->loadMissing('island.atoll');

        $details = [
            ['label' => 'Phone', 'value' => $profile->phone],
            ['label' => 'National ID card number', 'value' => $profile->national_id_no],
            ['label' => 'Address', 'value' => $profile->hasCompleteAddress() ? $profile->addressLine() : 'Not completed yet'],
        ];

        // A profile from before PB-53 that the user hasn't checked yet keeps its original text
        // on show, so nobody has to guess what the old address said.
        if ($profile->address_needs_review && $profile->address_legacy !== null) {
            $details[] = ['label' => 'Address as first entered', 'value' => $profile->address_legacy];
        }

        if ($profile instanceof BorrowerProfile) {
            $details = [
                ['label' => 'Borrowing as', 'value' => BorrowerProfile::TYPES[$profile->borrower_type]],
                ...$details,
                ...($profile->isSme() ? [
                    ['label' => 'Business name', 'value' => $profile->business_name],
                    ['label' => 'Business registration number', 'value' => $profile->business_registration_no],
                    ['label' => 'Sector', 'value' => BorrowerProfile::SECTORS[$profile->sector] ?? $profile->sector],
                    ['label' => 'Years operating', 'value' => $profile->years_operating],
                ] : []),
                [
                    'label' => $profile->isSme() ? 'Declared annual revenue' : 'Declared annual income',
                    'value' => 'MVR '.number_format((float) $profile->declared_annual_income, 2),
                ],
            ];
        }

        return $details;
    }
}
