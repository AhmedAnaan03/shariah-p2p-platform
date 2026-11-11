<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ChangePasswordController extends Controller
{
    /**
     * Show the change-password form.
     */
    public function edit(): Response
    {
        return Inertia::render('Account/ChangePassword');
    }

    /**
     * Save the new password. ChangePasswordRequest has already checked the current one.
     */
    public function update(ChangePasswordRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->validated('password'),
        ]);

        return back()->with('status', 'Your password has been changed.');
    }
}
