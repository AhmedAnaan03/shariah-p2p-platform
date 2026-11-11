<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Placeholder home page for each role's portal. Later backlog items replace these with real
 * dashboards; the routes and their role gates stay the same.
 */
class PortalController extends Controller
{
    public function borrower(): Response
    {
        return Inertia::render('Borrower/Portal');
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
