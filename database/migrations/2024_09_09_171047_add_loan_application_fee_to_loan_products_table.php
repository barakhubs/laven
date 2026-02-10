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
        Schema::table('loan_products', function (Blueprint $table) {
            $table->after('status', function (Blueprint $table) {
                $table->decimal('loan_application_fee', 10, 2)->default(0);
                $table->tinyInteger('loan_application_fee_type')->default(0)->comment('0 = Fixed | 1 = Percentage');
                $table->decimal('loan_processing_fee', 10, 2)->default(0);
                $table->tinyInteger('loan_processing_fee_type')->default(0)->comment('0 = Fixed | 1 = Percentage');
            });
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->after('borrower_id', function (Blueprint $table) {
                $table->unsignedBigInteger('debit_account_id')->nullable();
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->dropColumn(['loan_application_fee', 'loan_application_fee_type', 'loan_processing_fee', 'loan_processing_fee_type']);
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['debit_account_id']);
        });
    }
};
