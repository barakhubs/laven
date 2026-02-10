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
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->date('opening_date');
            $table->string('bank_name', 191);
            $table->bigInteger('currency_id')->unsigned();
            $table->string('account_name', 100);
            $table->string('account_number', 50)->nullable();
            $table->decimal('opening_balance', 10, 2);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('currency_id')->references('id')->on('currency')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
