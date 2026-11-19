<?php

namespace Tests\Concerns;

use App\Models\BorrowerProfile;
use App\Models\InvestorProfile;
use App\Models\KycDocument;
use App\Models\User;
use App\Support\DocumentSlots;

/**
 * Borrowers and investors at a given point of the onboarding wizard (PB-53), for tests that
 * need an account the admin can review without walking through every step.
 */
trait CreatesOnboardedUsers
{
    /**
     * A borrower who has finished every step: details, address, every document slot filled,
     * and (unless $submitted is false) submitted for verification.
     *
     * @param  array<string, mixed>  $attributes  for the user
     */
    protected function onboardedBorrower(array $attributes = [], bool $sme = false, bool $submitted = true): User
    {
        $factory = User::factory()->borrower();
        $user = ($submitted ? $factory->submitted() : $factory)->create($attributes);
        ($sme ? BorrowerProfile::factory() : BorrowerProfile::factory()->individual())->for($user)->create();

        return $this->fillSlots($user);
    }

    /**
     * An investor who has finished every step, risk acknowledgement included.
     *
     * @param  array<string, mixed>  $attributes  for the user
     */
    protected function onboardedInvestor(array $attributes = [], bool $submitted = true): User
    {
        $factory = User::factory()->investor();
        $user = ($submitted ? $factory->submitted() : $factory)->create($attributes);
        InvestorProfile::factory()->for($user)->create(['risk_acknowledged_at' => now()]);

        return $this->fillSlots($user);
    }

    /**
     * One pending document in each of the user's slots, except the types in $except.
     *
     * @param  list<string>  $except
     */
    protected function fillSlots(User $user, array $except = []): User
    {
        $user->unsetRelations();

        foreach (array_diff(DocumentSlots::typesFor($user), $except) as $type) {
            KycDocument::factory()->for($user)->ofType($type)->create(['original_name' => "{$type}.pdf"]);
        }

        return $user->unsetRelations();
    }
}
