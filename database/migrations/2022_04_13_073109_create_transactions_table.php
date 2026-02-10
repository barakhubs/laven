<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionsTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('member_id')->unsigned();
            $table->dateTime('trans_date');
            $table->bigInteger('savings_account_id')->unsigned()->nullable();
            $table->decimal('amount', 10, 2);
            $table->decimal('gateway_amount', 10, 2)->default(0);
            $table->string('dr_cr', 2);
            $table->string('type', 30);
            $table->string('method', 20);
            $table->tinyInteger('status');
            $table->text('note')->nullable();
            $table->text('description')->nullable();
            $table->bigInteger('loan_id')->nullable();
            $table->bigInteger('ref_id')->nullable();
            $table->bigInteger('parent_id')->unsigned()->nullable()->comment('Parent transaction id');
            $table->bigInteger('gateway_id')->nullable()->comment('PayPal | Stripe | Other Gateway');
            $table->bigInteger('created_user_id')->nullable();
            $table->bigInteger('updated_user_id')->nullable();
            $table->bigInteger('branch_id')->nullable();
            $table->text('transaction_details')->nullable();
            $table->string('tracking_id')->nullable();
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
            $table->foreign('savings_account_id')->references('id')->on('savings_accounts')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('transactions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('transactions');
    }
}
