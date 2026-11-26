<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Support\PlatformMode;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),

            // Only the fields the React pages need; never the whole model (no password hash,
            // remember token or encrypted fields can leak into the page's JSON).
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->name,
                    'role_label' => $user->role->label,
                    'status' => $user->status,
                    // The admin's reason after a rejection or suspension; shown to the user.
                    'status_reason' => $user->status_reason,
                ] : null,
            ],

            // One-off message set with ->with('status', '...') on a redirect.
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                // An information message rather than a success, e.g. why a campaign page sent
                // the investor back to the marketplace (PB-31).
                'notice' => fn () => $request->session()->get('notice'),
            ],

            // The platform-wide palette (PB-51). Not lazy: app.blade.php also reads it to put
            // data-mode on <html>, so the first paint is already in the right colours.
            'mode' => PlatformMode::for($user),

            // The theme choice (PB-55, PB-57): light, dark or null (follow the device), and the
            // device's setting when it was chosen. Not lazy: app.blade.php puts both on <html>
            // for the first paint. Guests follow their device.
            'theme' => ['choice' => $user?->theme, 'systemAtChoice' => $user?->theme_system_at_choice],

            // PB-34: the demo time tool's sidebar link, for administrators on a developer's
            // machine only (its routes exist only when APP_ENV is local).
            'demoTools' => app()->environment('local') && $user?->hasRole(Role::ADMIN),

            // The top bar's Shariah-only switch, for investors only. The preference is stored on
            // the investor profile, so it can only be changed once that profile exists.
            'modeSwitch' => $user?->hasRole(Role::INVESTOR) ? [
                'available' => $user->investorProfile !== null,
                'url' => route('investor.preferences.shariah-only'),
            ] : null,
        ];
    }
}
