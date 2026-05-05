<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\SavingsController;
use App\Http\Controllers\Api\DepositController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SmsController;
use App\Http\Controllers\PhoneOtpController;

Route::prefix('v1')->group(function () {

    Route::prefix('auth')->name('api.auth.')->group(function () {
        Route::post('login',           [AuthController::class, 'login'])->name('login');
        Route::post('register',        [AuthController::class, 'register'])->name('register');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('reset-password',  [AuthController::class, 'resetPassword'])->name('reset-password');
    });

    Route::prefix('auth')->name('api.auth.')->middleware('auth:sanctum')->group(function () {
        Route::post('verify-otp', [AuthController::class, 'verifyOtp'])->name('verify-otp');
        Route::post('resend-otp', [AuthController::class, 'resendOtp'])->name('resend-otp');
        Route::post('logout',     [AuthController::class, 'logout'])->name('logout');
    });

    Route::middleware(['auth:sanctum', 'api.2fa_verified'])->group(function () {
        Route::get('me',        [AuthController::class, 'me'])->name('api.me');

        // Staff-only: list/search clients
        Route::get('staff/clients', [AuthController::class, 'clients'])->name('api.staff.clients');

        Route::post('profile/update',          [ProfileController::class, 'apiUpdate'])->name('api.profile.update');
        Route::post('profile/update_password', [ProfileController::class, 'apiUpdatePassword'])->name('api.profile.update_password');

        Route::post('send-sms', [SmsController::class, 'send'])->name('api.send-sms');

        // Phone OTP for member registration (staff use, requires auth)
        Route::post('members/send-otp',   [PhoneOtpController::class, 'send'])->name('api.members.send_otp');
        Route::post('members/verify-otp', [PhoneOtpController::class, 'verify'])->name('api.members.verify_otp');

        // All data routes require client context resolution
        Route::middleware('staff.client.context')->group(function () {
            Route::get('dashboard', [DashboardController::class, 'index'])->name('api.dashboard');

            Route::get('notifications',                    [NotificationController::class, 'index'])->name('api.notifications.index');
            Route::post('notifications/{id}/read',         [NotificationController::class, 'markRead'])->name('api.notifications.read');
            Route::post('notifications/read-all',          [NotificationController::class, 'markAllRead'])->name('api.notifications.read-all');

            Route::get('loans',           [LoanController::class, 'index'])->name('api.loans.index');
            Route::post('loans/{id}/pay', [LoanController::class, 'pay'])->name('api.loans.pay');
            Route::get('loans/{id}',      [LoanController::class, 'show'])->name('api.loans.show');
            Route::get('transactions',    [TransactionController::class, 'index'])->name('api.transactions.index');
            Route::get('savings',                     [SavingsController::class, 'index'])->name('api.savings.index');
            Route::get('savings/{id}/transactions',   [SavingsController::class, 'transactions'])->name('api.savings.transactions');
            Route::get('deposit/methods',             [DepositController::class, 'methods'])->name('api.deposit.methods');
            Route::get('deposit/accounts',            [DepositController::class, 'accounts'])->name('api.deposit.accounts');
            Route::post('deposit/manual/{methodId?}', [DepositController::class, 'store'])->name('api.deposit.store');
            Route::get('deposit/history',             [DepositController::class, 'history'])->name('api.deposit.history');
        });
    });
});