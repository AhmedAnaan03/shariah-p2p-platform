<?php

namespace App\Http\Controllers;

use App\Support\VerificationChecklist;
use Illuminate\Http\Request;
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

    public function investor(): Response
    {
        return Inertia::render('Investor/Portal');
    }

    public function admin(): Response
    {
        return Inertia::render('Admin/Portal');
    }
}
