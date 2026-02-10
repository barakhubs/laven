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
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->after('status', function (Blueprint $table) {
                $table->timestamp('upcomming_notification')->nullable();
                $table->timestamp('overdue_notification')->nullable();
                $table->timestamp('final_notification')->nullable();
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->dropColumn(['upcomming_notification', 'overdue_notification', 'final_notification']);
        });
    }
};
