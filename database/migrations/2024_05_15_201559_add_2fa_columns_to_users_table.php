<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->after('password', function (Blueprint $table) {
                $table->string('two_factor_code', 10)->nullable();
                $table->dateTime('two_factor_expires_at')->nullable();
                $table->integer('two_factor_code_count')->default(0);
                $table->string('otp', 10)->nullable();
                $table->dateTime('otp_expires_at')->nullable();
                $table->integer('otp_count')->default(0);
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_code', 'two_factor_expires_at', 'otp', 'otp_expires_at']);
        });
    }
};
