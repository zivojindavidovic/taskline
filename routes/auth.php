<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\OnboardingController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    // Guided first-run onboarding (ported from the auth.html prototype).
    // Behind `auth` only — never `verified`/`onboarded` — so a user mid-setup
    // can always reach the step they belong on.
    Route::prefix('onboarding')->name('onboarding.')->group(function () {
        // Step 1 (Cloud): verify email via OTP
        Route::get('verify', [OnboardingController::class, 'showVerify'])->name('verify');
        Route::post('verify', [OnboardingController::class, 'verify'])->name('verify.confirm');
        Route::post('verify/resend', [OnboardingController::class, 'resend'])->name('verify.resend');

        // Step 2 (Cloud): welcome gate + pending invitations
        Route::get('gate', [OnboardingController::class, 'showGate'])->name('gate');
        Route::post('gate/accept', [OnboardingController::class, 'acceptInvitation'])->name('gate.accept');
        Route::post('gate/decline', [OnboardingController::class, 'declineInvitation'])->name('gate.decline');

        // Step 3 (both): create workspace
        Route::get('workspace', [OnboardingController::class, 'showWorkspace'])->name('workspace');
        Route::post('workspace', [OnboardingController::class, 'storeWorkspace'])->name('workspace.store');

        // Step 4 (Self-hosted): create team accounts directly
        Route::get('team', [OnboardingController::class, 'showTeam'])->name('team');
        Route::post('team', [OnboardingController::class, 'storeTeam'])->name('team.store');

        // Done
        Route::get('done', [OnboardingController::class, 'showDone'])->name('done');
    });
});
