<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use App\Observers\AuditObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Schema::defaultStringLength(191);
    }

    public function boot(): void
    {
        Paginator::useBootstrap();

        // ----------------------------------------------------------------
        // Audit Observer — fires on every create / update / delete
        // across all models listed below.
        // ----------------------------------------------------------------
        $modelsToAudit = [
            \App\Models\User::class,
            \App\Models\Member::class,
            \App\Models\MemberDocument::class,
            \App\Models\Loan::class,
            \App\Models\LoanPayment::class,
            \App\Models\LoanRepayment::class,
            \App\Models\LoanCollateral::class,
            \App\Models\LoanProduct::class,
            \App\Models\Guarantor::class,
            \App\Models\Transaction::class,
            \App\Models\TransactionCategory::class,
            \App\Models\SavingsAccount::class,
            \App\Models\SavingsProduct::class,
            \App\Models\DepositRequest::class,
            \App\Models\DepositMethod::class,
            \App\Models\WithdrawRequest::class,
            \App\Models\WithdrawMethod::class,
            \App\Models\Expense::class,
            \App\Models\ExpenseCategory::class,
            \App\Models\BankAccount::class,
            \App\Models\BankTransaction::class,
            \App\Models\Branch::class,
            \App\Models\Currency::class,
            \App\Models\Role::class,
            \App\Models\AccessControl::class,
            \App\Models\PaymentGateway::class,
            \App\Models\Setting::class,
            \App\Models\CustomField::class,
            \App\Models\ChargeLimit::class,
            \App\Models\InterestPosting::class,
            \App\Models\EmailSMSTemplate::class,
            \App\Models\Navigation::class,
            \App\Models\NavigationItem::class,
            \App\Models\Page::class,
        ];

        foreach ($modelsToAudit as $model) {
            $model::observe(AuditObserver::class);
        }
    }
}

