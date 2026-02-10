<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLoanPaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('loan_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('loan_id')->unsigned();
            $table->date('paid_at');
            $table->decimal('late_penalties',10,2);
            $table->decimal('interest',10,2);
            $table->decimal('repayment_amount',10,2);
            $table->decimal('total_amount',10,2);
            $table->text('remarks')->nullable();
            $table->bigInteger('member_id')->unsigned();
            $table->bigInteger('transaction_id')->nullable();
            $table->bigInteger('repayment_id');
            $table->timestamps();

            $table->foreign('loan_id')->references('id')->on('loans')->onDelete('cascade');
            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('loan_payments');
    }
}
