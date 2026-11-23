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
     * @return list<array{label: string, value: mixed, mono?: bool}>|null null if there is no profile yet.
     */
    public static function details(User $user): ?array
    {
        $profile = VerificationChecklist::for($user)->profile();

        if ($profile === null) {
            return null;
        }

        $profile->loadMissing('island.atoll');

        $details = [
            ['label' => 'Phone', 'value' => $profile->phone, 'mono' => true],
            ['label' => 'National ID card number', 'value' => $profile->national_id_no, 'mono' => true],
            ['label' => 'Address', 'value' => $profile->hasCompleteAddress() ? $profile->addressLine() : 'Not completed yet', 'mono' => false],
        ];

        if ($profile instanceof BorrowerProfile) {
            $details = [
                ['label' => 'Borrowing as', 'value' => BorrowerProfile::TYPES[$profile->borrower_type], 'mono' => false],
                ...$details,
                ...($profile->isSme() ? [
                    ['label' => 'Business name', 'value' => $profile->business_name, 'mono' => false],
                    ['label' => 'Business registration number', 'value' => $profile->business_registration_no, 'mono' => true],
                    ['label' => 'Sector', 'value' => BorrowerProfile::SECTORS[$profile->sector] ?? $profile->sector, 'mono' => false],
                    ['label' => 'Years operating', 'value' => $profile->years_operating, 'mono' => true],
                ] : []),
                // The financial profile (PB-58), checked with the salary slip or financial
                // statement and, for the credit history, the MMA credit report.
                ...($profile->hasFinancialProfile()
                    ? [...CampaignPresenter::finances($profile), ...CampaignPresenter::creditHistory($profile)]
                    : [['label' => 'Financial profile', 'value' => 'Not completed yet', 'mono' => false]]),
            ];
        }

        return $details;
    }
}
