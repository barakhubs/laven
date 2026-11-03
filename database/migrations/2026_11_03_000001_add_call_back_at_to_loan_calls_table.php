<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When to call the client again, chosen by whoever logged the call.
 * On a collection call it also sets how long the client stays locked.
 */
class AddCallBackAtToLoanCallsTable extends Migration
{
    public function up()
    {
        Schema::table('loan_calls', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_calls', 'call_back_at')) {
                $table->dateTime('call_back_at')->nullable()->after('locked_until');
            }
        });
    }

    public function down()
    {
        Schema::table('loan_calls', function (Blueprint $table) {
            $table->dropColumn('call_back_at');
        });
    }
}
