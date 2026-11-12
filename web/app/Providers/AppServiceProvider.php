<?php

namespace App\Providers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
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
     * this with ->can('pledge'); the investor portal already uses it to show whether pledging
     * is unlocked. Each refusal says what is missing.
     */
    private function definePledgeGate(): void
    {
        Gate::define('pledge', function (User $user): Response {
            if (! $user->hasRole(Role::INVESTOR)) {
                return Response::deny('Only investors can pledge.');
            }

            if ($user->investorProfile?->risk_acknowledged_at === null) {
                return Response::deny('Accept the risk acknowledgement before you pledge.');
            }

            if ($user->status !== 'verified') {
                return Response::deny('Your account must be verified before you can pledge.');
            }

            return Response::allow();
        });
    }
}
