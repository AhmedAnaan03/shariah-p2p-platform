<?php

namespace App\Support;

use App\Models\Campaign;
use App\Models\Role;
use App\Models\User;

/**
 * Which palette the whole interface uses for a user: conventional (slate and navy) or Shariah
 * (moss and muted green). See docs/design-system/README.md, "Modes".
 *
 * One place decides it, so the server-rendered <html data-mode> (first paint) and the `mode`
 * prop React reads can never disagree.
 *
 * - Investors: Shariah when they have turned on Shariah-only (investor_profiles.shariah_only).
 * - Borrowers: Shariah when their most recent financing request is a Murabahah or Mudarabah
 *   (PB-13). While they fill in a new request, the page follows the contract they pick.
 * - Everyone else, and guests: conventional for now. The admin Shariah settings (PB-32) will
 *   switch administrators later.
 */
final class PlatformMode
{
    public const CONVENTIONAL = 'conventional';

    public const SHARIAH = 'shariah';

    public static function for(?User $user): string
    {
        if ($user?->hasRole(Role::INVESTOR) && $user->investorProfile?->shariah_only) {
            return self::SHARIAH;
        }

        if ($user?->hasRole(Role::BORROWER)
            && Campaign::isShariahContract($user->campaigns()->latest('id')->value('contract_type'))) {
            return self::SHARIAH;
        }

        return self::CONVENTIONAL;
    }
}
