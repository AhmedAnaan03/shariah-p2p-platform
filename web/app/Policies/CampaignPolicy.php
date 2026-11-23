<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Who may create and see financing requests (PB-13). The borrower routes are already behind
 * role:borrower; these rules add "verified" and "only your own".
 */
class CampaignPolicy
{
    public const NO_FINANCIAL_PROFILE = 'Complete your financial profile in account setup before you submit a financing request.';

    public const NOT_VERIFIED = 'Your account must be verified before you can submit a financing request.';

    /**
     * Only a verified borrower may submit a request (PB-13 acceptance criterion).
     */
    public function create(User $user): Response
    {
        if (! $user->hasRole(Role::BORROWER)) {
            return Response::deny('Only borrowers can submit financing requests.');
        }

        if ($user->status !== 'verified') {
            return Response::deny(self::NOT_VERIFIED);
        }

        // PB-58: every request carries a snapshot of the financial profile, so a borrower verified
        // before the profile existed must complete it first (which sends it for checking).
        return $user->borrowerProfile?->hasFinancialProfile()
            ? Response::allow()
            : Response::deny(self::NO_FINANCIAL_PROFILE);
    }

    /**
     * A borrower sees only their own requests. Someone else's answers 404, not 403, so request
     * ids can't be probed (the same rule as for documents, PB-09).
     */
    public function view(User $user, Campaign $campaign): Response
    {
        return $user->id === $campaign->borrower_id ? Response::allow() : Response::denyAsNotFound();
    }
}
