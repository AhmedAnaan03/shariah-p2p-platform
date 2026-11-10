<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    /**
     * Show the registration form.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            'roles' => Role::whereIn('name', RegisterRequest::SELF_REGISTER_ROLES)
                ->orderBy('id')
                ->get(['name', 'label']),
        ]);
    }

    /**
     * Create the account, sign the user in and send them to their portal. By the time this
     * runs, RegisterRequest has already validated every field.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        // Only name, email and password are mass-assignable. role_id is set explicitly from
        // the validated role, and status is left to its default ('pending_verification').
        $user = new User($request->safe()->only(['name', 'email', 'password']));
        $user->role_id = Role::idFor($request->validated('role'));
        $user->save();

        event(new Registered($user));

        Auth::login($user);
        // A fresh session id on sign-in prevents session fixation.
        $request->session()->regenerate();

        return redirect()
            ->route($user->portalRoute())
            ->with('status', 'Your account has been created.');
    }
}
