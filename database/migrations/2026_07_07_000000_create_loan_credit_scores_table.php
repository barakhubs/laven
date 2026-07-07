<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLoanCreditScoresTable extends Migration {

    public function up() {
        Schema::create('loan_credit_scores', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('loan_id')->unique();
            $table->unsignedBigInteger('borrower_id');
            $table->decimal('score', 5, 2)->default(100);
            $table->integer('on_time_count')->default(0);
            $table->integer('late_count')->default(0);
            $table->integer('overdue_count')->default(0);
            $table->integer('total_schedules')->default(0);
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();

            $table->foreign('loan_id')->references('id')->on('loans')->onDelete('cascade');
        });
    }

    public function down() {
        Schema::dropIfExists('loan_credit_scores');
    }
}