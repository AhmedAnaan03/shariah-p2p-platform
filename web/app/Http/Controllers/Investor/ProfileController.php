<?php

namespace App\Http\Controllers\Investor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\InvestorProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The investor profile (PB-10). Reached only through the investor portal group.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Investor/Profile', [
            'profile' => $user->investorProfile?->makeVisible('national_id_no')->only(['phone', 'national_id_no', 'address']),
            'locked' => ! $user->canEditVerificationDetails(),
        ]);
    }

    public function update(InvestorProfileRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->investorProfile()->updateOrCreate([], $request->profileData());
        $user->reopenReviewIfRejected();

        return to_route('investor.profile.edit')->with('status', 'Your profile has been saved.');
    }
}
