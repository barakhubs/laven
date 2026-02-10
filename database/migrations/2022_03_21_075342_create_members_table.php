<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMembersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->bigInteger('branch_id')->nullable();
            $table->bigInteger('user_id')->nullable();
            $table->string('email', 100)->nullable();
            $table->string('country_code', 10)->nullable();
            $table->string('mobile', 50)->nullable();
            $table->string('business_name', 100)->nullable();
            $table->string('member_no', 50)->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('city', 191)->nullable();
            $table->string('state', 191)->nullable();
            $table->string('zip', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('credit_source', 191)->nullable();
            $table->string('photo', 191)->nullable();
            $table->text('custom_fields')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('members');
    }
}
