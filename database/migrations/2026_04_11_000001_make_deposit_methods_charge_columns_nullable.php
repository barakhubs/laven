<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeDepositMethodsChargeColumnsNullable extends Migration
{
    public function up()
    {
        Schema::table('deposit_methods', function (Blueprint $table) {
            $table->decimal('minimum_amount', 10, 2)->nullable()->change();
            $table->decimal('maximum_amount', 10, 2)->nullable()->change();
            $table->decimal('fixed_charge', 10, 2)->nullable()->change();
            $table->decimal('charge_in_percentage', 10, 2)->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('deposit_methods', function (Blueprint $table) {
            $table->decimal('minimum_amount', 10, 2)->nullable(false)->change();
            $table->decimal('maximum_amount', 10, 2)->nullable(false)->change();
            $table->decimal('fixed_charge', 10, 2)->nullable(false)->change();
            $table->decimal('charge_in_percentage', 10, 2)->nullable(false)->change();
        });
    }
}