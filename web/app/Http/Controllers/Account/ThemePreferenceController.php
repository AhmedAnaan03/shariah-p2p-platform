<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ThemePreferenceRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Saves the signed-in user's theme (PB-55). Any role. The browser has already switched the
 * colours; this makes the choice stick, so the server can paint the next page in it.
 */
class ThemePreferenceController extends Controller
{
    public function __invoke(ThemePreferenceRequest $request): RedirectResponse
    {
        $user = $request->user();
        // Not mass-assignable: set on its own, from the validated value.
        $user->theme = $request->validated('theme');
        $user->save();

        return back();
    }
}
