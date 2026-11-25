<?php

namespace App\Providers;

use App\Models\Campaign;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /** Pledge gate refusals for a particular campaign (PB-31). */
    public const PLEDGE_CLOSED = 'This campaign is not open for pledges.';

    public const PLEDGE_SHARIAH_ONLY = 'Shariah-only mode is on, so you can only pledge to Murabahah and Mudarabah campaigns.';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // One password policy for registration, reset and change password. Each form uses
        // Password::defaults(), so the rule can never drift between them.
        // uncompromised() is left out on purpose: it calls the external Have I Been Pwned API,
        // which would make validation (and CI) depend on a third-party service being up.
        Password::defaults(fn () => Password::min(8)
            ->mixedCase()
            ->numbers()
            ->symbols());

        $this->definePledgeGate();
    }

    /**
     * Who may pledge to a campaign (PB-10). The pledge route itself is PB-19, which attaches
     * this with ->can('pledge', 'campaign'); the marketplace and campaign page already use it to
     * show whether pledging is unlocked. Each refusal says what is missing.
     *
     * Without a campaign it answers "may this investor pledge at all?". With one it also checks
     * that campaign (PB-31): it must be open, and a Shariah-only investor can only pledge to a
     * Murabahah or Mudarabah, so hiding a card is never the only protection.
     */
    private function definePledgeGate(): void
    {
        Gate::define('pledge', function (User $user, ?Campaign $campaign = null): Response {
            if (! $user->hasRole(Role::INVESTOR)) {
                return Response::deny('Only investors can pledge.');
            }

            if ($user->investorProfile?->risk_acknowledged_at === null) {
                return Response::deny('Accept the risk acknowledgement before you pledge.');
            }

            if ($user->status !== 'verified') {
                return Response::deny('Your account must be verified before you can pledge.');
            }

            if ($campaign !== null && ! $campaign->isListed()) {
                return Response::deny(self::PLEDGE_CLOSED);
            }

            if ($campaign !== null && $user->investorProfile->shariah_only && ! $campaign->isShariah()) {
                return Response::deny(self::PLEDGE_SHARIAH_ONLY);
            }

            return Response::allow();
        });
    }
}
