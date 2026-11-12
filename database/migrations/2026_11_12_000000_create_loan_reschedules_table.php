<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Loan rescheduling (LoanRescheduleService). A reschedule keeps the same
 * loan: what its unpaid installments still owed is moved onto new ones.
 *
 *  - loan_reschedules: one row per reschedule, with the reason, who did it,
 *    and the old and new figures.
 *  - loan_repayments:
 *      closed_by_reschedule_id   the reschedule that closed this installment;
 *      created_by_reschedule_id  the reschedule that created it;
 *      principal_moved / interest_moved
 *                                what was still owed on it and moved to the
 *                                new installments (like *_waived, it's no
 *                                longer owed on this one);
 *      penalty_moved             penalty it owed, taken off by the reschedule:
 *                                kept (penalty_kept) or waived (the rest);
 *                                interest_moved likewise covers interest
 *                                kept (interest_kept) or waived;
 *      penalty_carried           its share of the penalty kept from the old
 *                                installments, spread evenly over the new
 *                                ones (no interest on it, never grows).
 */
class CreateLoanReschedulesTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('loan_reschedules')) {
            Schema::create('loan_reschedules', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('loan_id');
                $table->date('rescheduled_on');
                $table->date('first_date');
                $table->unsignedInteger('installments');
                $table->text('reason');
                $table->decimal('principal', 15, 2);
                $table->decimal('amount', 15, 2);                    // rescheduled, before extension interest
                $table->decimal('interest_carried', 15, 2);          // unpaid interest owed before
                $table->decimal('interest_kept', 15, 2);             // of it, in the new plan; the rest waived
                $table->decimal('rate', 12, 8);
                $table->unsignedInteger('extra_periods')->default(0);
                $table->decimal('extra_interest', 15, 2)->default(0);
                $table->decimal('penalty', 15, 2)->default(0);       // owed before
                $table->decimal('penalty_kept', 15, 2)->default(0);  // in the new plan; the rest waived
                $table->decimal('old_total', 15, 2);
                $table->decimal('new_total', 15, 2);
                $table->date('old_end_date')->nullable();
                $table->boolean('client_notified')->default(false);
                $table->unsignedBigInteger('created_user_id')->nullable();
                $table->timestamps();

                $table->index('loan_id');
            });
        }

        Schema::table('loan_repayments', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_repayments', 'closed_by_reschedule_id')) {
                $table->unsignedBigInteger('closed_by_reschedule_id')->nullable();
                $table->unsignedBigInteger('created_by_reschedule_id')->nullable();
                $table->decimal('principal_moved', 10, 2)->default(0);
                $table->decimal('interest_moved', 10, 2)->default(0);
                $table->decimal('penalty_moved', 10, 2)->default(0);
                $table->decimal('penalty_carried', 10, 2)->default(0);
            }
        });
    }

    public function down()
    {
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->dropColumn(['closed_by_reschedule_id', 'created_by_reschedule_id', 'principal_moved', 'interest_moved', 'penalty_moved', 'penalty_carried']);
        });
        Schema::dropIfExists('loan_reschedules');
    }
}
