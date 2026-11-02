<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-time codes sent by SMS so members can sign in with their phone number
 * instead of a password (see App\Services\MemberOtpLogin). Codes are stored
 * hashed; rows are kept after use so sends can be counted for rate limits.
 */
class CreateLoginCodesTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('login_codes')) {
            Schema::create('login_codes', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->index();
                $table->string('code');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('used_at')->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('login_codes');
    }
}
