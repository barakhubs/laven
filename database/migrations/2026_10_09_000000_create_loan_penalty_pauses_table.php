<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Days on which an installment's late penalty does not accrue (see
 * LoanRepayment::penaltyAccrued). Two kinds:
 *  - grace:   granted by staff, from the day it's entered, with a reason;
 *  - payment: the automatic pause after a qualifying payment, removed with
 *             that payment when it's reversed.
 * One row per pause; the installments it covers are in the pivot table.
 * Penalty-free days are starts_on..ends_on inclusive.
 */
class CreateLoanPenaltyPausesTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('loan_penalty_pauses')) {
            Schema::create('loan_penalty_pauses', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('loan_id');
                $table->string('type', 20);
                $table->unsignedBigInteger('loan_payment_id')->nullable();
                $table->date('starts_on');
                $table->date('ends_on');
                $table->text('reason')->nullable();
                $table->unsignedBigInteger('created_user_id')->nullable();
                $table->timestamp('ended_early_at')->nullable();
                $table->unsignedBigInteger('ended_by')->nullable();
                $table->timestamps();

                $table->foreign('loan_payment_id')->references('id')->on('loan_payments')->onDelete('cascade');
                $table->index(['loan_id', 'type']);
                $table->index(['type', 'starts_on', 'ends_on']);
            });
        }

        if (! Schema::hasTable('loan_penalty_pause_repayments')) {
            Schema::create('loan_penalty_pause_repayments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('loan_penalty_pause_id');
                $table->unsignedBigInteger('loan_repayment_id');

                $table->foreign('loan_penalty_pause_id')->references('id')->on('loan_penalty_pauses')->onDelete('cascade');
                $table->index('loan_repayment_id');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('loan_penalty_pause_repayments');
        Schema::dropIfExists('loan_penalty_pauses');
    }
}
