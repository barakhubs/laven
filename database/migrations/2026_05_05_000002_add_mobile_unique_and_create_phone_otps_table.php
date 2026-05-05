<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddMobileUniqueAndCreatePhoneOtpsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * - Enforces cross-branch uniqueness on members.mobile
     * - Creates phone_otps table for registration phone verification
     */
    public function up()
    {
        // Remove duplicate mobile numbers before adding unique constraint
        // (keep the earliest record per mobile, nullify the rest)
        DB::statement("
            UPDATE members m
            JOIN (
                SELECT mobile, MIN(id) AS keep_id
                FROM members
                WHERE mobile IS NOT NULL AND mobile != ''
                GROUP BY mobile
                HAVING COUNT(*) > 1
            ) dups ON m.mobile = dups.mobile AND m.id != dups.keep_id
            SET m.mobile = NULL
        ");

        Schema::table('members', function (Blueprint $table) {
            $table->unique('mobile', 'members_mobile_unique');
        });

        Schema::create('phone_otps', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 30)->index();
            $table->string('code', 10);
            $table->boolean('verified')->default(false);
            $table->string('override_by')->nullable()->comment('Staff name who bypassed OTP');
            $table->string('override_reason')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('phone_otps');

        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique('members_mobile_unique');
        });
    }
}