<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the columns the web portal, mobile API and cron jobs look rows up by.
 *
 * Production runs on PostgreSQL, which (unlike MySQL) does not index foreign key columns on
 * its own, and several tables (loans, loan_repayments) never had any. Each index is added only
 * when the table and columns exist and no existing index already starts with those columns,
 * so this is safe on both databases and on installs that already have some of them.
 */
return new class extends Migration
{
    /** table => list of column lists (each list becomes one index, leading column first). */
    private const INDEXES = [
        'loans' => [['borrower_id', 'status'], ['status', 'currency_id'], ['loan_product_id'], ['debit_account_id'], ['branch_id'], ['release_date'], ['created_user_id']],
        'loan_repayments' => [['loan_id', 'status'], ['status', 'repayment_date'], ['repayment_date']],
        'loan_payments' => [['loan_id'], ['member_id'], ['paid_at'], ['repayment_id'], ['transaction_id']],
        'loan_payment_allocations' => [['loan_payment_id'], ['loan_repayment_id']],
        'loan_collaterals' => [['loan_id']],
        'loan_credit_scores' => [['loan_id'], ['borrower_id']],
        'guarantors' => [['loan_id'], ['member_id', 'savings_account_id'], ['savings_account_id']],
        'transactions' => [['member_id', 'trans_date'], ['savings_account_id', 'status', 'dr_cr'], ['loan_id'], ['parent_id'], ['trans_date'], ['branch_id'], ['type', 'status']],
        'savings_accounts' => [['member_id'], ['savings_product_id']],
        'members' => [['user_id'], ['branch_id', 'status'], ['member_no'], ['loan_officer_id'], ['email']],
        'users' => [['user_type'], ['branch_id'], ['role_id']],
        'deposit_requests' => [['member_id'], ['status'], ['credit_account_id'], ['method_id']],
        'withdraw_requests' => [['member_id'], ['status'], ['debit_account_id'], ['method_id'], ['transaction_id']],
        'member_documents' => [['member_id']],
        'device_tokens' => [['user_id']],
        'notifications' => [['notifiable_id', 'read_at']],
        'audit_logs' => [['user_id'], ['created_at']],
        'bank_transactions' => [['bank_account_id'], ['trans_date']],
        'expenses' => [['expense_date'], ['branch_id'], ['expense_category_id']],
        'settings' => [['name']],
        'email_sms_templates' => [['slug']],
        'permissions' => [['role_id']],
        'schedule_tasks_histories' => [['name', 'reference_id']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $existing = array_map(fn ($i) => $i['columns'], Schema::getIndexes($table));

            foreach ($indexes as $columns) {
                if (! Schema::hasColumns($table, $columns) || $this->covered($existing, $columns)) {
                    continue;
                }
                Schema::table($table, fn (Blueprint $t) => $t->index($columns, $this->name($table, $columns)));
                $existing[] = $columns;
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($indexes as $columns) {
                $name = $this->name($table, $columns);
                if (Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
                }
            }
        }
    }

    /** An index on (a, b, c) already serves lookups on (a) and (a, b). */
    private function covered(array $existing, array $columns): bool
    {
        foreach ($existing as $cols) {
            if (array_slice($cols, 0, count($columns)) === $columns) {
                return true;
            }
        }
        return false;
    }

    /** Names are unique per database in PostgreSQL, so they carry the table name. */
    private function name(string $table, array $columns): string
    {
        return substr('perf_' . $table . '_' . implode('_', $columns), 0, 60);
    }
};
