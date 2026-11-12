<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\VerificationChecklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The home page of each role's portal. For now it shows the verification checklist (PB-09 to
 * PB-11); later items add dashboards. The routes and their role gates stay the same.
 */
class PortalController extends Controller
{
    public function borrower(Request $request): Response
    {
        return Inertia::render('Borrower/Portal', [
            'checklist' => VerificationChecklist::for($request->user())->toArray(),
        ]);
    }

    public function investor(Request $request): Response
    {
        $user = $request->user();
        // inspect() runs the 'pledge' gate and returns its answer with the reason, without
        // throwing, so the page can show why pledging is still locked.
        $pledge = Gate::forUser($user)->inspect('pledge');

        return Inertia::render('Investor/Portal', [
            'checklist' => VerificationChecklist::for($user)->toArray(),
            'riskAcknowledged' => $user->investorProfile?->risk_acknowledged_at !== null,
            'pledge' => ['allowed' => $pledge->allowed(), 'reason' => $pledge->message()],
        ]);
    }

    public function admin(): Response
    {
        $readyForReview = User::awaitingVerification()
            ->get()
            ->filter(fn (User $user) => VerificationChecklist::for($user)->isComplete())
            ->count();

        return Inertia::render('Admin/Portal', [
            'readyForReview' => $readyForReview,
        ]);
    }
}
