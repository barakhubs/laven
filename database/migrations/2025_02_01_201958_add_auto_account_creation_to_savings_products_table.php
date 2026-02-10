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
        Schema::table('savings_products', function (Blueprint $table) {
            $table->tinyInteger('auto_create')->default(0)->after('maintenance_fee_posting_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('savings_products', function (Blueprint $table) {
            $table->dropColumn(['auto_create']);
        });
    }
};
