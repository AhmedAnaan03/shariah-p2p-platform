<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    /**
     * Show the placeholder landing page until the role portals exist.
     */
    public function __invoke(): Response
    {
        return Inertia::render('Welcome', [
            'roles' => [
                [
                    'name' => 'Borrower / SME',
                    'description' => 'Raise financing for a business or personal need.',
                ],
                [
                    'name' => 'Investor',
                    'description' => 'Fund campaigns and track returns.',
                ],
                [
                    'name' => 'Administrator',
                    'description' => 'Verify users, review campaigns and monitor the platform.',
                ],
            ],
        ]);
    }
}
