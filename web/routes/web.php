<?php

use App\Http\Controllers\Admin\VerificationController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Borrower\ProfileController as BorrowerProfileController;
use App\Http\Controllers\Investor\ProfileController as InvestorProfileController;
use App\Http\Controllers\Investor\RiskAcknowledgementController;
use App\Http\Controllers\KycDocumentController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\WelcomeController;
use App\Models\Role;
use Illuminate\Support\Facades\Route;

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

    // Opens one KYC file. Shared by the owner and admins, so it sits outside the role groups;
    // KycDocumentPolicy lets only those two through and answers 404 to everyone else.
    Route::get('/documents/{document}', [KycDocumentController::class, 'show'])->name('documents.show');
});

/*
| One portal per role. Every route a later item adds for a role goes inside its group, so it
| inherits the gate: e.g. a borrower opening /investor/... gets 403 Forbidden.
*/
Route::middleware(['auth', 'role:'.Role::BORROWER])->prefix('borrower')->name('borrower.')->group(function () {
    Route::get('/', [PortalController::class, 'borrower'])->name('portal');

    Route::get('/profile', [BorrowerProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [BorrowerProfileController::class, 'update'])->name('profile.update');

    Route::get('/documents', [KycDocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents', [KycDocumentController::class, 'store'])->name('documents.store');
});

Route::middleware(['auth', 'role:'.Role::INVESTOR])->prefix('investor')->name('investor.')->group(function () {
    Route::get('/', [PortalController::class, 'investor'])->name('portal');

    Route::get('/profile', [InvestorProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [InvestorProfileController::class, 'update'])->name('profile.update');

    // Same controller as the borrower's documents; it uses the signed-in user's role.
    Route::get('/documents', [KycDocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents', [KycDocumentController::class, 'store'])->name('documents.store');

    Route::get('/acknowledgement', [RiskAcknowledgementController::class, 'show'])->name('acknowledgement.show');
    Route::post('/acknowledgement', [RiskAcknowledgementController::class, 'store'])->name('acknowledgement.store');
});

Route::middleware(['auth', 'role:'.Role::ADMIN])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [PortalController::class, 'admin'])->name('portal');

    Route::get('/verifications', [VerificationController::class, 'index'])->name('verifications.index');
    Route::get('/verifications/{user}', [VerificationController::class, 'show'])->name('verifications.show');
    Route::post('/verifications/{user}/decision', [VerificationController::class, 'decide'])->name('verifications.decide');
});
