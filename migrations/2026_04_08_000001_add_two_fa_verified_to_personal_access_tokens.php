<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add `two_fa_verified` to the personal_access_tokens table.
 *
 * This flag is set to false on token creation when 2FA is enabled,
 * and flipped to true once the user successfully verifies their OTP
 * via POST /api/v1/auth/verify-otp.
 *
 * Routes protected by the `api.2fa_verified` middleware check this
 * column before allowing access.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->boolean('two_fa_verified')->default(false)->after('abilities');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn('two_fa_verified');
        });
    }
};