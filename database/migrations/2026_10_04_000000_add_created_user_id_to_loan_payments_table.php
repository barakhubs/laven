<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who recorded a loan payment, so a receipt reprinted later (or by the
 * client from the portal) still shows the staff member who took it.
 */
class AddCreatedUserIdToLoanPaymentsTable extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('loan_payments', 'created_user_id')) {
            Schema::table('loan_payments', function (Blueprint $table) {
                $table->unsignedBigInteger('created_user_id')->nullable();
            });
        }
    }

    public function down()
    {
        Schema::table('loan_payments', function (Blueprint $table) {
            $table->dropColumn('created_user_id');
        });
    }
}
