<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Calls staff make to clients about due and overdue installments (see
 * LoanCall). locked_until is worked out from the outcome when the call is
 * logged; until then the client is greyed out on Due Payments so nobody
 * else calls them again.
 *
 * Every role that can open Due Payments is also allowed to log calls.
 */
class CreateLoanCallsTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('loan_calls')) {
            Schema::create('loan_calls', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('loan_id');
                $table->unsignedBigInteger('member_id');
                $table->string('outcome', 20);
                $table->date('promise_date')->nullable();
                $table->decimal('promise_amount', 15, 2)->nullable();
                $table->text('note')->nullable();
                $table->timestamp('locked_until')->nullable();
                $table->unsignedBigInteger('created_user_id')->nullable();
                $table->timestamps();

                $table->index(['member_id', 'created_at']);
                $table->index('loan_id');
            });
        }

        $now = now();
        DB::table('permissions')->where('permission', 'loans.due_payments')->pluck('role_id')->unique()
            ->each(function ($roleId) use ($now) {
                $exists = DB::table('permissions')->where('role_id', $roleId)->where('permission', 'loan_calls.create')->exists();
                if (! $exists) {
                    DB::table('permissions')->insert(['role_id' => $roleId, 'permission' => 'loan_calls.create', 'created_at' => $now, 'updated_at' => $now]);
                }
            });
    }

    public function down()
    {
        DB::table('permissions')->where('permission', 'loan_calls.create')->delete();
        Schema::dropIfExists('loan_calls');
    }
}
