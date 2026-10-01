<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an installment be paid in several parts. Until now any payment closed
 * its installment, so a client whose penalty + interest exceeded what they
 * brought in could not be recorded at all. These columns hold how much of the
 * installment's penalty, interest and principal has already been collected
 * while it is still open, so the next payment only asks for the rest.
 */
class AddPaidColumnsToLoanRepaymentsTable extends Migration
{
    public function up()
    {
        // hasColumn guards make a re-run safe if a failed deploy left some
        // of the columns behind.
        Schema::table('loan_repayments', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_repayments', 'penalty_paid')) {
                $table->decimal('penalty_paid', 10, 2)->default(0);
            }
            if (! Schema::hasColumn('loan_repayments', 'interest_paid')) {
                $table->decimal('interest_paid', 10, 2)->default(0);
            }
            if (! Schema::hasColumn('loan_repayments', 'principal_paid')) {
                $table->decimal('principal_paid', 10, 2)->default(0);
            }
        });

        // Every installment closed so far was closed by a single payment that
        // cleared its interest and principal in full. Plain correlated
        // subquery so this runs on both MySQL and PostgreSQL.
        DB::table('loan_repayments')->where('status', 1)->update([
            'interest_paid'  => DB::raw('interest'),
            'principal_paid' => DB::raw('principal_amount'),
            'penalty_paid'   => DB::raw('(SELECT COALESCE(SUM(loan_payments.late_penalties), 0) FROM loan_payments WHERE loan_payments.repayment_id = loan_repayments.id)'),
        ]);
    }

    public function down()
    {
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->dropColumn(['penalty_paid', 'interest_paid', 'principal_paid']);
        });
    }
}
