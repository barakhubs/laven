<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\SavingsController;
use App\Http\Controllers\Api\DepositController;

/*
|--------------------------------------------------------------------------
| API Routes – Mobile App Authentication
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api (via RouteServiceProvider).
| Auth routes are versioned under /v1/auth.
|
| Middleware groups:
|   auth:sanctum         → valid Sanctum bearer token required
|   api.2fa_verified     → token must have two_fa_verified = true
|
*/

Route::prefix('v1')->group(function () {

    // ----------------------------------------------------------------
    // Public auth endpoints (no token required)
    // ----------------------------------------------------------------
    Route::prefix('auth')->name('api.auth.')->group(function () {

        Route::post('login',          [AuthController::class, 'login'])->name('login');
        Route::post('register',       [AuthController::class, 'register'])->name('register');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('reset-password',  [AuthController::class, 'resetPassword'])->name('reset-password');
    });

    // ----------------------------------------------------------------
    // Semi-protected: valid token required, but 2FA not yet verified
    // (used during the OTP verification step)
    // ----------------------------------------------------------------
    Route::prefix('auth')->name('api.auth.')->middleware('auth:sanctum')->group(function () {

        Route::post('verify-otp', [AuthController::class, 'verifyOtp'])->name('verify-otp');
        Route::post('resend-otp', [AuthController::class, 'resendOtp'])->name('resend-otp');
        Route::post('logout',     [AuthController::class, 'logout'])->name('logout');
    });

    // ----------------------------------------------------------------
    // Fully protected: token required + 2FA verified
    // ----------------------------------------------------------------
    Route::middleware(['auth:sanctum', 'api.2fa_verified'])->group(function () {
    Route::get('me', [AuthController::class, 'me'])->name('api.me');

    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index'])->name('api.dashboard');

    // Loans
    Route::get('loans',      [LoanController::class, 'index'])->name('api.loans.index');
    Route::get('loans/{id}', [LoanController::class, 'show'])->name('api.loans.show');

    // Transactions
    Route::get('transactions', [TransactionController::class, 'index'])->name('api.transactions.index');

    // Savings
    Route::get('savings',                          [SavingsController::class, 'index'])->name('api.savings.index');
    Route::get('savings/{id}/transactions',        [SavingsController::class, 'transactions'])->name('api.savings.transactions');

    // Deposits
    Route::get('deposit/methods',                  [DepositController::class, 'methods'])->name('api.deposit.methods');
    Route::get('deposit/accounts',                 [DepositController::class, 'accounts'])->name('api.deposit.accounts');
    Route::post('deposit/manual/{methodId?}',      [DepositController::class, 'store'])->name('api.deposit.store');
    Route::get('deposit/history',                  [DepositController::class, 'history'])->name('api.deposit.history');
});
});