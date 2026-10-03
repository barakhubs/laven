<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interest staff waive when a client pays a loan off early, kept like the
 * penalty waiver: per installment (so it isn't owed again), per payment
 * allocation (so a reversal restores it) and on the payment (receipt).
 */
class AddInterestWaivedColumns extends Migration
{
    public function up()
    {
        foreach (['loan_repayments', 'loan_payments', 'loan_payment_allocations'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'interest_waived')) {
                    $table->decimal('interest_waived', 10, 2)->default(0);
                }
            });
        }
    }

    public function down()
    {
        foreach (['loan_repayments', 'loan_payments', 'loan_payment_allocations'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'interest_waived')) {
                    $table->dropColumn('interest_waived');
                }
            });
        }
    }
}
