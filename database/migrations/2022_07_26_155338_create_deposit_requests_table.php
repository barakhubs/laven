<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDepositRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('deposit_requests', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('member_id')->unsigned();
            $table->bigInteger('method_id')->unsigned();
            $table->bigInteger('credit_account_id')->unsigned();
            $table->decimal('amount', 10, 2);
            $table->decimal('converted_amount', 10, 2);
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->string('attachment')->nullable();
            $table->tinyInteger('status')->default(0);
            $table->bigInteger('transaction_id')->nullable();
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
            $table->foreign('method_id')->references('id')->on('deposit_methods')->onDelete('cascade');
            $table->foreign('credit_account_id')->references('id')->on('savings_accounts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('deposit_requests');
    }
}
