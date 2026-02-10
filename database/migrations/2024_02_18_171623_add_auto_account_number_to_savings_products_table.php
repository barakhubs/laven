<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table('savings_products', function (Blueprint $table) {
            $table->after('name', function (Blueprint $table) {
                $table->string('account_number_prefix', 10)->nullable();
                $table->bigInteger('starting_account_number')->nullable();
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::table('savings_products', function (Blueprint $table) {
            $table->dropColumn(['account_number_prefix', 'starting_account_number']);
        });
    }
};
