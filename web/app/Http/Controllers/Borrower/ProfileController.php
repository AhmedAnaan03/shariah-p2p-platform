<?php

namespace App\Http\Controllers\Borrower;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\BorrowerProfileRequest;
use App\Models\BorrowerProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The borrower / SME profile (PB-09). Reached only through the borrower portal group, so the
 * user is always a signed-in borrower.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->borrowerProfile;

        return Inertia::render('Borrower/Profile', [
            // The owner sees their own ID number to edit it, so it is made visible here only.
            'profile' => $profile?->makeVisible('national_id_no')->only([
                'borrower_type', 'phone', 'national_id_no', 'address', 'business_name',
                'business_registration_no', 'sector', 'years_operating', 'declared_annual_income',
            ]),
            'borrowerTypes' => self::options(BorrowerProfile::TYPES),
            'sectors' => self::options(BorrowerProfile::SECTORS),
            'locked' => ! $user->canEditVerificationDetails(),
        ]);
    }

    /**
     * Create the profile the first time, update it after that.
     */
    public function update(BorrowerProfileRequest $request): RedirectResponse
    {
        $user = $request->user();

        // updateOrCreate on the hasOne relation fills in user_id itself.
        $user->borrowerProfile()->updateOrCreate([], $request->profileData());
        $user->reopenReviewIfRejected();

        return to_route('borrower.profile.edit')->with('status', 'Your profile has been saved.');
    }

    /**
     * ['key' => 'Label'] → [{ value, label }] for the Select and RadioGroup components.
     *
     * @param  array<string, string>  $labels
     * @return list<array{value: string, label: string}>
     */
    private static function options(array $labels): array
    {
        return array_map(fn ($value, $label) => ['value' => $value, 'label' => $label], array_keys($labels), $labels);
    }
}
