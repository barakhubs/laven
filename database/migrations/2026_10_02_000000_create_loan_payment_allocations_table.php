<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One payment can now cover several installments (oldest first, any excess
 * carried to the next), so each payment records what it put on each
 * installment. That's what lets a payment be reversed exactly and shown on
 * a receipt. Also adds:
 *  - loan_repayments.penalty_waived: penalty staff waived, so it isn't owed again
 *  - loan_repayments.cleared_at: date the installment was fully paid (credit score)
 *  - loan_payments.penalty_waived: waiver given with that payment
 */
class CreateLoanPaymentAllocationsTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('loan_payment_allocations')) {
            Schema::create('loan_payment_allocations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('loan_payment_id');
                $table->unsignedBigInteger('loan_repayment_id');
                $table->decimal('penalty', 10, 2)->default(0);
                $table->decimal('penalty_waived', 10, 2)->default(0);
                $table->decimal('interest', 10, 2)->default(0);
                $table->decimal('principal', 10, 2)->default(0);
                $table->timestamps();

                $table->foreign('loan_payment_id')->references('id')->on('loan_payments')->onDelete('cascade');
                $table->index('loan_repayment_id');
            });
        }

        Schema::table('loan_repayments', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_repayments', 'penalty_waived')) {
                $table->decimal('penalty_waived', 10, 2)->default(0);
            }
            if (! Schema::hasColumn('loan_repayments', 'cleared_at')) {
                $table->date('cleared_at')->nullable();
            }
        });

        Schema::table('loan_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_payments', 'penalty_waived')) {
                $table->decimal('penalty_waived', 10, 2)->default(0);
            }
        });

        // Installments already paid were closed by the payment(s) recorded
        // against them; the latest one is when they were cleared.
        DB::table('loan_repayments')->where('status', 1)->whereNull('cleared_at')->update([
            'cleared_at' => DB::raw('(SELECT MAX(loan_payments.paid_at) FROM loan_payments WHERE loan_payments.repayment_id = loan_repayments.id)'),
        ]);

        // Every existing payment was against the single installment in its
        // repayment_id. Recording that as an allocation lets old and new
        // payments be reversed the same way.
        DB::statement('
            INSERT INTO loan_payment_allocations (loan_payment_id, loan_repayment_id, penalty, penalty_waived, interest, principal, created_at, updated_at)
            SELECT p.id, p.repayment_id, p.late_penalties, 0, p.interest, p.repayment_amount - p.interest, p.created_at, p.updated_at
            FROM loan_payments p
            WHERE NOT EXISTS (SELECT 1 FROM loan_payment_allocations a WHERE a.loan_payment_id = p.id)
        ');
    }

    public function down()
    {
        Schema::dropIfExists('loan_payment_allocations');

        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->dropColumn(['penalty_waived', 'cleared_at']);
        });

        Schema::table('loan_payments', function (Blueprint $table) {
            $table->dropColumn('penalty_waived');
        });
    }
}
