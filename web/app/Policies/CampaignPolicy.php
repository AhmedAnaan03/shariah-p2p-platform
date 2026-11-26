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

    /** The response code browse() uses for a campaign hidden by Shariah-only mode (PB-31). */
    public const HIDDEN_BY_SHARIAH_ONLY = 'hidden_by_shariah_only';

    public const SHARIAH_ONLY_HIDDEN = 'That campaign uses a conventional contract, so it is hidden while Shariah-only mode is on. Turn Shariah-only off in the top bar to see it.';

    public const SHARIAH_ONLY_PLEDGED = 'This campaign uses a conventional contract. You can still see it because you pledged to it, but it stays hidden from the marketplace while Shariah-only mode is on, and you can’t pledge to it again.';

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

    /**
     * Whether an investor may open a campaign's page (PB-17, PB-31, PB-19).
     *
     * - Their own investment: an investor who has pledged to a campaign can always open it,
     *   whatever its status (live, funded, failed, repaying, completed, defaulted) and even if
     *   Shariah-only mode now hides it. The page then says where the campaign stands.
     * - Anyone else, not open (draft, waiting, rejected, suspended, funded, failed, past its
     *   deadline): 404, as if it didn't exist, so unpublished requests can't be found by trying
     *   ids.
     * - Conventional while Shariah-only is on: refused with its own code, so the controller
     *   can send the investor back to the marketplace with an explanation instead of an error.
     *
     * The marketplace itself still lists open campaigns only (Campaign::visibleTo()).
     */
    public function browse(User $user, Campaign $campaign): Response
    {
        if (! $user->hasRole(Role::INVESTOR)) {
            return Response::denyAsNotFound();
        }

        if (self::hasPledged($user, $campaign)) {
            return Response::allow();
        }

        if (! $campaign->isListed()) {
            return Response::denyAsNotFound();
        }

        if ($user->investorProfile?->shariah_only && ! $campaign->isShariah()) {
            return Response::deny(self::SHARIAH_ONLY_HIDDEN, self::HIDDEN_BY_SHARIAH_ONLY);
        }

        return Response::allow();
    }

    /**
     * Whether $user has ever pledged to $campaign, including pledges since refunded.
     */
    public static function hasPledged(User $user, Campaign $campaign): bool
    {
        return $campaign->pledges()->where('investor_id', $user->id)->exists();
    }
}
