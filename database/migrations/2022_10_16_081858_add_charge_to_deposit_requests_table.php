<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddChargeToDepositRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('deposit_requests', function (Blueprint $table) {
            $table->decimal('charge', 10, 2)->after('converted_amount')->nullable();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('charge', 10, 2)->after('savings_account_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('deposit_requests', function (Blueprint $table) {
            $table->dropColumn('charge');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('charge');
        });
    }
}
