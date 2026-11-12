<?php

namespace App\Http\Controllers\Investor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Investor\RiskAcknowledgementRequest;
use App\Models\AuditLog;
use App\Support\RiskAcknowledgement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The capital-at-risk acknowledgement an investor must accept before pledging (PB-10).
 * Accepting sets investor_profiles.risk_acknowledged_at, which the 'pledge' gate checks.
 */
class RiskAcknowledgementController extends Controller
{
    public function show(Request $request): Response
    {
        $profile = $request->user()->investorProfile;

        return Inertia::render('Investor/Acknowledgement', [
            'acknowledgement' => RiskAcknowledgement::toArray(),
            'hasProfile' => $profile !== null,
            'acknowledgedAt' => $profile?->risk_acknowledged_at?->toIso8601String(),
        ]);
    }

    public function store(RiskAcknowledgementRequest $request): RedirectResponse
    {
        $profile = $request->user()->investorProfile;

        // The timestamp lives on the profile, so the profile has to exist first.
        if ($profile === null) {
            return to_route('investor.profile.edit')
                ->with('status', 'Complete your profile first, then accept the risk acknowledgement.');
        }

        // Accepting twice keeps the first date: that is when the investor first agreed.
        if ($profile->risk_acknowledged_at === null) {
            DB::transaction(function () use ($profile) {
                // Not mass-assignable: set in code, never from the request.
                $profile->risk_acknowledged_at = now();
                $profile->save();

                AuditLog::record('investor.risk_acknowledged', $profile, [
                    'text_version' => RiskAcknowledgement::VERSION,
                ]);
            });
        }

        return to_route('investor.portal')->with('status', 'Thank you. You have accepted the risk acknowledgement.');
    }
}
