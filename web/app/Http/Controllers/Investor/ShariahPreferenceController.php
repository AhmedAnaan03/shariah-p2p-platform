<?php

namespace App\Http\Controllers\Investor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Investor\ShariahPreferenceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * Saves the investor's Shariah-only choice from the top bar's mode switch (PB-51).
 *
 * The switch has already changed the palette in the browser; this makes it stick, so the next
 * page (and the next sign-in) starts in the same mode. The route has no user id in it: it can
 * only change the signed-in investor's own profile. Hiding non-Shariah campaigns is PB-31.
 */
class ShariahPreferenceController extends Controller
{
    public function __invoke(ShariahPreferenceRequest $request): RedirectResponse
    {
        $profile = $request->user()->investorProfile;

        // The preference is a column on the profile, so the profile has to exist first. The
        // switch is disabled until then; this covers a request sent some other way.
        if ($profile === null) {
            throw ValidationException::withMessages([
                'shariah_only' => 'Complete your profile first, then choose Shariah-only mode.',
            ]);
        }

        $profile->update(['shariah_only' => $request->boolean('shariah_only')]);

        return back()->with('status', $profile->shariah_only
            ? 'Shariah-only mode is on.'
            : 'Shariah-only mode is off.');
    }
}
