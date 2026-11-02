<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extra phone numbers (people who aren't members or staff) that Bulk SMS
 * can send to alongside everyone in the system. Stored as +256... so the
 * same number can't be added twice in different formats.
 */
class CreateSmsContactsTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('sms_contacts')) {
            Schema::create('sms_contacts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 100)->nullable();
                $table->string('phone', 20)->unique();
                $table->unsignedBigInteger('created_user_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('sms_contacts');
    }
}
