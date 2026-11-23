<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;

/**
 * The onboarding wizard's steps, in order, and where a user is in them (PB-53):
 *
 *   borrower  profile → address → documents → review
 *   investor  profile → address → documents → risk → review
 *
 * A step is done when VerificationChecklist says its part is provided; review is done once the
 * account has been submitted for verification. The user can open any step up to and including
 * the first one that isn't done, so they can go back and change earlier answers but can't skip
 * ahead. Opening the wizard (or a step they can't reach yet) takes them to that first step, which
 * is how leaving and coming back resumes where they left off.
 */
final class OnboardingSteps
{
    public const PROFILE = 'profile';

    public const ADDRESS = 'address';

    public const DOCUMENTS = 'documents';

    public const RISK = 'risk';

    public const REVIEW = 'review';

    /** Every step any role can have, for the route constraint. */
    public const ALL = [self::PROFILE, self::ADDRESS, self::DOCUMENTS, self::RISK, self::REVIEW];

    private const TITLES = [
        self::PROFILE => 'Your details',
        self::ADDRESS => 'Address',
        self::DOCUMENTS => 'Documents',
        self::RISK => 'Risk acknowledgement',
        self::REVIEW => 'Review and submit',
    ];

    private readonly VerificationChecklist $checklist;

    private function __construct(private readonly User $user)
    {
        $this->checklist = VerificationChecklist::for($user);
    }

    public static function for(User $user): self
    {
        return new self($user);
    }

    /**
     * @return list<string>
     */
    public static function stepsForRole(string $role): array
    {
        return $role === Role::INVESTOR
            ? [self::PROFILE, self::ADDRESS, self::DOCUMENTS, self::RISK, self::REVIEW]
            : [self::PROFILE, self::ADDRESS, self::DOCUMENTS, self::REVIEW];
    }

    /**
     * @return list<string>
     */
    public function steps(): array
    {
        return self::stepsForRole($this->user->role->name);
    }

    public function has(string $step): bool
    {
        return in_array($step, $this->steps(), true);
    }

    public function isDone(string $step): bool
    {
        return match ($step) {
            self::PROFILE => $this->checklist->hasProfile(),
            self::ADDRESS => $this->checklist->hasCompleteAddress(),
            self::DOCUMENTS => $this->checklist->hasAllDocuments(),
            self::RISK => $this->checklist->hasAcceptedRisk(),
            self::REVIEW => $this->checklist->isSubmitted() || $this->user->status === 'verified',
        };
    }

    /**
     * The first step that isn't done, where the wizard resumes. When every step is done (the
     * account is submitted or verified) it is the review step, which then shows the status.
     */
    public function current(): string
    {
        // A verified account is locked: every step is open to read, and the wizard opens on the
        // review step.
        if ($this->user->status === 'verified') {
            return self::REVIEW;
        }

        foreach ($this->steps() as $step) {
            if (! $this->isDone($step)) {
                return $step;
            }
        }

        return self::REVIEW;
    }

    /**
     * A step the user may open now: any step up to the current one. Later steps depend on
     * earlier answers (the document slots depend on the borrower type, for example).
     */
    public function canOpen(string $step): bool
    {
        $steps = $this->steps();

        return $this->has($step) && array_search($step, $steps, true) <= array_search($this->current(), $steps, true);
    }

    /**
     * The step after this one, or null after the last.
     */
    public function next(string $step): ?string
    {
        $steps = $this->steps();

        return $steps[array_search($step, $steps, true) + 1] ?? null;
    }

    public function previous(string $step): ?string
    {
        $steps = $this->steps();
        $index = array_search($step, $steps, true);

        return $index > 0 ? $steps[$index - 1] : null;
    }

    public function url(string $step): string
    {
        return route("{$this->user->role->name}.onboarding.show", $step);
    }

    /**
     * For the OnboardingStepper: each step with its title, whether it is done, and a link when
     * the user can open it.
     *
     * @return list<array{key: string, title: string, done: bool, href: ?string}>
     */
    public function toArray(): array
    {
        return array_map(fn (string $step) => [
            'key' => $step,
            'title' => self::TITLES[$step],
            'done' => $this->isDone($step),
            'href' => $this->canOpen($step) ? $this->url($step) : null,
        ], $this->steps());
    }
}
