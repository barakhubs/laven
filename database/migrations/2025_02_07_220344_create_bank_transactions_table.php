<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->date('trans_date');
            $table->unsignedBigInteger('bank_account_id');
            $table->decimal('amount', 10, 2);
            $table->string('dr_cr', 2);
            $table->string('type', 30)->comment('deposit | withdraw | transfer');
            $table->string('cheque_number', 50)->nullable();
            $table->string('attachment', 191)->nullable();
            $table->tinyInteger('status')->default(1);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_user_id');
            $table->timestamps();

            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
