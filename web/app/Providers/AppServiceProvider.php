<?php

namespace App\Providers;

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
    }
}
