<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calls can now be logged from any client list, not just Due Payments.
 * A call is either about money due on a loan ('collection', as before:
 * it locks the client for a while) or a 'general' call with no loan,
 * which is only recorded.
 */
class AddTypeToLoanCallsTable extends Migration
{
    public function up()
    {
        Schema::table('loan_calls', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_calls', 'type')) {
                $table->string('type', 20)->default('collection')->after('member_id');
            }
            $table->unsignedBigInteger('loan_id')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('loan_calls', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
}
