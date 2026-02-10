<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Create Super Admin Role
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'Super Admin',
            'description' => 'Super Administrator with full access to the system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create Super Admin User
        $userId = DB::table('users')->insertGetId([
            'name' => 'Super Admin',
            'email' => 'admin@admin.com',
            'user_type' => 'admin',
            'role_id' => $roleId,
            'branch_id' => null,
            'status' => 1,
            'profile_picture' => null,
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'provider' => null,
            'provider_id' => null,
            'remember_token' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Grant all permissions to Super Admin role
        // Common permissions for a microfinance system
        $permissions = [
            // Dashboard
            'dashboard.index',

            // Users Management
            'users.index',
            'users.create',
            'users.store',
            'users.edit',
            'users.update',
            'users.destroy',

            // Roles Management
            'roles.index',
            'roles.create',
            'roles.store',
            'roles.edit',
            'roles.update',
            'roles.destroy',

            // Permissions Management
            'permissions.create',
            'permissions.store',

            // Members Management
            'members.index',
            'members.create',
            'members.store',
            'members.edit',
            'members.update',
            'members.destroy',
            'members.show',
            'members.import',

            // Savings Products
            'savings_products.index',
            'savings_products.create',
            'savings_products.store',
            'savings_products.edit',
            'savings_products.update',
            'savings_products.destroy',

            // Savings Accounts
            'savings_accounts.index',
            'savings_accounts.create',
            'savings_accounts.store',
            'savings_accounts.edit',
            'savings_accounts.update',
            'savings_accounts.destroy',
            'savings_accounts.show',

            // Loan Products
            'loan_products.index',
            'loan_products.create',
            'loan_products.store',
            'loan_products.edit',
            'loan_products.update',
            'loan_products.destroy',

            // Loans
            'loans.index',
            'loans.create',
            'loans.store',
            'loans.edit',
            'loans.update',
            'loans.destroy',
            'loans.show',
            'loans.approve',
            'loans.decline',
            'loans.disbursement',

            // Loan Repayments
            'loan_repayments.index',
            'loan_repayments.create',
            'loan_repayments.store',

            // Transactions
            'transactions.index',
            'transactions.create',
            'transactions.store',
            'transactions.edit',
            'transactions.update',
            'transactions.destroy',
            'transactions.show',

            // Deposits
            'deposit_requests.index',
            'deposit_requests.show',
            'deposit_requests.approve',
            'deposit_requests.reject',

            // Withdrawals
            'withdraw_requests.index',
            'withdraw_requests.show',
            'withdraw_requests.approve',
            'withdraw_requests.reject',

            // Deposit Methods
            'deposit_methods.index',
            'deposit_methods.create',
            'deposit_methods.store',
            'deposit_methods.edit',
            'deposit_methods.update',
            'deposit_methods.destroy',

            // Withdraw Methods
            'withdraw_methods.index',
            'withdraw_methods.create',
            'withdraw_methods.store',
            'withdraw_methods.edit',
            'withdraw_methods.update',
            'withdraw_methods.destroy',

            // Branches
            'branches.index',
            'branches.create',
            'branches.store',
            'branches.edit',
            'branches.update',
            'branches.destroy',

            // Expenses
            'expenses.index',
            'expenses.create',
            'expenses.store',
            'expenses.edit',
            'expenses.update',
            'expenses.destroy',

            // Expense Categories
            'expense_categories.index',
            'expense_categories.create',
            'expense_categories.store',
            'expense_categories.edit',
            'expense_categories.update',
            'expense_categories.destroy',

            // Payment Gateways
            'payment_gateways.index',
            'payment_gateways.edit',
            'payment_gateways.update',

            // Settings
            'settings.index',
            'settings.update',
            'settings.update_profile',
            'settings.update_password',

            // Reports
            'reports.index',
            'reports.transactions_report',
            'reports.loans_report',
            'reports.savings_report',
            'reports.revenue_report',

            // Database Backup
            'database_backups.index',
            'database_backups.create',
            'database_backups.destroy',
            'database_backups.download',

            // Email/SMS Templates
            'email_sms_templates.index',
            'email_sms_templates.edit',
            'email_sms_templates.update',

            // Notifications
            'notifications.index',
            'notifications.show',

            // Pages
            'pages.index',
            'pages.create',
            'pages.store',
            'pages.edit',
            'pages.update',
            'pages.destroy',

            // Navigations
            'navigations.index',
            'navigations.create',
            'navigations.store',
            'navigations.edit',
            'navigations.update',
            'navigations.destroy',

            // Interest Posting
            'interest_posting.index',
            'interest_posting.post',

            // Bank Accounts
            'bank_accounts.index',
            'bank_accounts.create',
            'bank_accounts.store',
            'bank_accounts.edit',
            'bank_accounts.update',
            'bank_accounts.destroy',

            // Bank Transactions
            'bank_transactions.index',
            'bank_transactions.create',
            'bank_transactions.store',
            'bank_transactions.edit',
            'bank_transactions.update',
            'bank_transactions.destroy',
        ];

        // Insert permissions
        $permissionData = [];
        foreach ($permissions as $permission) {
            $permissionData[] = [
                'role_id' => $roleId,
                'permission' => $permission,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($permissionData)) {
            DB::table('permissions')->insert($permissionData);
        }

        $this->command->info('Super Admin created successfully!');
        $this->command->info('Email: admin@admin.com');
        $this->command->info('Password: password');
        $this->command->warn('Please change the password after first login!');
    }
}
