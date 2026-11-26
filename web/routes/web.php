<?php

use App\Http\Controllers\Account\ThemePreferenceController;
use App\Http\Controllers\Admin\CampaignController as AdminCampaignController;
use App\Http\Controllers\Admin\DemoControlsController;
use App\Http\Controllers\Admin\VerificationController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Borrower\CampaignController as BorrowerCampaignController;
use App\Http\Controllers\Investor\CampaignController as InvestorCampaignController;
use App\Http\Controllers\Investor\PledgeController;
use App\Http\Controllers\Investor\ShariahPreferenceController;
use App\Http\Controllers\KycDocumentController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\Wallet\WalletController;
use App\Http\Controllers\WelcomeController;
use App\Models\Role;
use App\Support\OnboardingSteps;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', WelcomeController::class)->name('home');

/*
| Guest-only pages. 'guest' sends a signed-in user to their own portal instead.
*/
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);

    Route::get('/login', [SessionController::class, 'create'])->name('login');
    // Rate limiting for sign-in lives in LoginRequest (per email + IP).
    Route::post('/login', [SessionController::class, 'store']);

    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    // At most 6 reset requests a minute from one client, so the form can't be used to flood
    // inboxes. (The broker also allows only one email per account per minute.)
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    // 'password.reset' is the name Laravel's ResetPassword email uses to build its link.
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.store');
});

/*
| Any signed-in user, whatever their role.
*/
Route::middleware('auth')->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');

    Route::get('/account/password', [ChangePasswordController::class, 'edit'])->name('password.edit');
    Route::put('/account/password', [ChangePasswordController::class, 'update'])->name('password.update');

    // The sidebar's light / dark / system toggle (PB-55). No user id in the URL: it always
    // changes the signed-in user's own setting.
    Route::patch('/account/theme', ThemePreferenceController::class)->name('account.theme');

    // Opens one KYC file. Shared by the owner and admins, so it sits outside the role groups;
    // KycDocumentPolicy lets only those two through and answers 404 to everyone else.
    Route::get('/documents/{document}', [KycDocumentController::class, 'show'])->name('documents.show');
});

/*
| The onboarding wizard (PB-53), registered inside the borrower and the investor groups below so
| it inherits their role gate. One controller; the user's role picks the steps. The old
| profile and documents pages now open the matching step, so earlier links still work.
*/
// A closure, not a named function: tests boot the app many times and would redeclare it.
$onboardingRoutes = function (string $role): void {
    Route::get('/onboarding', [OnboardingController::class, 'start'])->name('onboarding');
    Route::get('/onboarding/{step}', [OnboardingController::class, 'show'])
        ->whereIn('step', OnboardingSteps::stepsForRole($role))
        ->name('onboarding.show');
    Route::put('/onboarding/profile', [OnboardingController::class, 'saveProfile'])->name('onboarding.profile');
    Route::put('/onboarding/address', [OnboardingController::class, 'saveAddress'])->name('onboarding.address');
    Route::post('/onboarding/documents', [OnboardingController::class, 'uploadDocument'])->name('onboarding.documents.upload');
    Route::put('/onboarding/documents', [OnboardingController::class, 'saveDocuments'])->name('onboarding.documents.save');
    Route::post('/onboarding/submit', [OnboardingController::class, 'submit'])->name('onboarding.submit');

    Route::get('/profile', fn () => to_route("{$role}.onboarding.show", OnboardingSteps::PROFILE))->name('profile.edit');
    Route::get('/documents', fn () => to_route("{$role}.onboarding.show", OnboardingSteps::DOCUMENTS))->name('documents.index');
};

/*
| The simulated wallet (PB-18), for borrowers and investors, registered inside each group below so
| it inherits the role gate. No user id in the URLs: it is always the signed-in user's own wallet.
*/
$walletRoutes = function (): void {
    Route::get('/wallet', [WalletController::class, 'show'])->name('wallet.show');
    Route::post('/wallet/top-up', [WalletController::class, 'topUp'])->name('wallet.top-up');
    Route::post('/wallet/withdraw', [WalletController::class, 'withdraw'])->name('wallet.withdraw');
};

/*
| One portal per role. Every route a later item adds for a role goes inside its group, so it
| inherits the gate: e.g. a borrower opening /investor/... gets 403 Forbidden.
*/
Route::middleware(['auth', 'role:'.Role::BORROWER])->prefix('borrower')->name('borrower.')->group(function () use ($onboardingRoutes, $walletRoutes) {
    Route::get('/', [PortalController::class, 'borrower'])->name('portal');

    $walletRoutes();

    $onboardingRoutes(Role::BORROWER);

    // The financial profile is the borrower's own onboarding step (PB-58).
    Route::put('/onboarding/finances', [OnboardingController::class, 'saveFinances'])->name('onboarding.finances');

    // Financing requests (PB-13, PB-14). Any borrower can open the pages; only a verified one
    // can submit (CampaignPolicy), and each borrower sees only their own requests.
    Route::get('/campaigns', [BorrowerCampaignController::class, 'index'])->name('campaigns.index');
    Route::get('/campaigns/create', [BorrowerCampaignController::class, 'create'])->name('campaigns.create');
    Route::post('/campaigns', [BorrowerCampaignController::class, 'store'])->name('campaigns.store');
    Route::get('/campaigns/{campaign}', [BorrowerCampaignController::class, 'show'])->name('campaigns.show');
});

Route::middleware(['auth', 'role:'.Role::INVESTOR])->prefix('investor')->name('investor.')->group(function () use ($onboardingRoutes, $walletRoutes) {
    Route::get('/', [PortalController::class, 'investor'])->name('portal');

    $walletRoutes();

    $onboardingRoutes(Role::INVESTOR);

    // The risk acknowledgement is the investor's last onboarding step (PB-53).
    Route::post('/onboarding/risk', [OnboardingController::class, 'acceptRisk'])->name('onboarding.risk');
    Route::get('/acknowledgement', fn () => to_route('investor.onboarding.show', OnboardingSteps::RISK))->name('acknowledgement.show');

    // The top bar's Shariah-only switch (PB-51). No user id in the URL: it always changes the
    // signed-in investor's own profile.
    Route::patch('/preferences/shariah-only', ShariahPreferenceController::class)->name('preferences.shariah-only');

    // The marketplace (PB-15, PB-16) and a campaign's page (PB-17). Both list only what the
    // investor may see: open campaigns, and only Shariah ones while Shariah-only is on (PB-31).
    Route::get('/campaigns', [InvestorCampaignController::class, 'index'])->name('campaigns.index');
    Route::get('/campaigns/{campaign}', [InvestorCampaignController::class, 'show'])->whereNumber('campaign')->name('campaigns.show');
    // PB-19: pledge from the wallet into the campaign's escrow.
    Route::post('/campaigns/{campaign}/pledges', [PledgeController::class, 'store'])->whereNumber('campaign')
        ->middleware('can:pledge,campaign')->name('campaigns.pledges.store');
});

Route::middleware(['auth', 'role:'.Role::ADMIN])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [PortalController::class, 'admin'])->name('portal');

    Route::get('/verifications', [VerificationController::class, 'index'])->name('verifications.index');
    Route::get('/verifications/{user}', [VerificationController::class, 'show'])->name('verifications.show');
    Route::post('/verifications/{user}/decision', [VerificationController::class, 'decide'])->name('verifications.decide');

    // Campaign review (PB-15): approve, reject or suspend.
    Route::get('/campaigns', [AdminCampaignController::class, 'index'])->name('campaigns.index');
    Route::get('/campaigns/{campaign}', [AdminCampaignController::class, 'show'])->name('campaigns.show');
    Route::post('/campaigns/{campaign}/decision', [AdminCampaignController::class, 'decide'])->name('campaigns.decide');
});

/*
| Design preview (PB-51): every shared component in every state, in both modes, for checking
| the UI and taking report screenshots. Registered on a developer's machine only, never in
| production or in tests (APP_ENV=testing), so it can't leak into the live app.
*/
if (app()->environment('local')) {
    Route::get('/dev/design', fn () => Inertia::render('Dev/Design'))->name('dev.design');

    /*
    | DEMO TOOL (PB-34): move a repaying campaign's schedule so its next row is due now or
    | overdue, to demonstrate repayments and late payments without waiting. Administrators only,
    | and like the design preview it exists only on a developer's machine: never in production,
    | never in the tests.
    */
    Route::middleware(['auth', 'role:'.Role::ADMIN])->prefix('admin/demo')->name('admin.demo.')->group(function () {
        Route::get('/', [DemoControlsController::class, 'index'])->name('index');
        Route::post('/campaigns/{campaign}/advance', [DemoControlsController::class, 'advance'])->whereNumber('campaign')->name('advance');
    });
}
