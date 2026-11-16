<?php

namespace App\Support;

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
 * - Everyone else, and guests: conventional for now. The borrower's contract choice (PB-13) and
 *   the admin Shariah settings (PB-32) will switch it later.
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

        return self::CONVENTIONAL;
    }
}
