<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\User;
use App\Support\OnboardingSteps;
use App\Support\VerificationChecklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The home page of each role's portal. For now it shows the onboarding steps (PB-09 to PB-11,
 * PB-53); later items add dashboards. The routes and their role gates stay the same.
 */
class PortalController extends Controller
{
    public function borrower(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Borrower/Portal', [
            'onboarding' => self::onboarding($user),
            'requestCount' => $user->campaigns()->count(),
        ]);
    }

    public function investor(Request $request): Response
    {
        $user = $request->user();
        // inspect() runs the 'pledge' gate and returns its answer with the reason, without
        // throwing, so the page can show why pledging is still locked.
        $pledge = Gate::forUser($user)->inspect('pledge');

        return Inertia::render('Investor/Portal', [
            'onboarding' => self::onboarding($user),
            'pledge' => ['allowed' => $pledge->allowed(), 'reason' => $pledge->message()],
        ]);
    }

    public function admin(): Response
    {
        $readyForReview = User::awaitingVerification()
            ->get()
            ->filter(fn (User $user) => VerificationChecklist::for($user)->isReadyForReview())
            ->count();

        return Inertia::render('Admin/Portal', [
            'readyForReview' => $readyForReview,
            'campaignsToReview' => Campaign::where('status', Campaign::STATUS_SUBMITTED)->count(),
        ]);
    }

    /**
     * The wizard's steps for the portal's stepper and the "continue" link (PB-53).
     *
     * @return array{steps: list<array<string, mixed>>, continueUrl: string}
     */
    private static function onboarding(User $user): array
    {
        $steps = OnboardingSteps::for($user);

        return ['steps' => $steps->toArray(), 'continueUrl' => $steps->url($steps->current())];
    }
}
