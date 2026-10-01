<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every loan product keeps 30% of the applied amount as a savings reserve
 * for the final installments, except YKN loans. Products can now opt out.
 */
class AddRequiresSavingsReserveToLoanProductsTable extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('loan_products', 'requires_savings_reserve')) {
            Schema::table('loan_products', function (Blueprint $table) {
                $table->boolean('requires_savings_reserve')->default(true);
            });
        }

        DB::table('loan_products')->whereRaw("UPPER(loan_id_prefix) = 'YKN'")->update(['requires_savings_reserve' => false]);
    }

    public function down()
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->dropColumn('requires_savings_reserve');
        });
    }
}
