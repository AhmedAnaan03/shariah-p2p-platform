<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ThemePreferenceRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Saves the signed-in user's theme choice (PB-55, PB-57) with the device's setting at the time.
 * Any role. The browser has already switched the colours; this makes the choice stick, so the
 * next page is painted in it, unless the device's setting changes in the meantime.
 */
class ThemePreferenceController extends Controller
{
    public function __invoke(ThemePreferenceRequest $request): RedirectResponse
    {
        $user = $request->user();
        // Not mass-assignable: set on its own, from the validated value.
        $user->theme = $request->validated('theme');
        $user->theme_system_at_choice = $request->validated('system');
        $user->save();

        return back();
    }
}
