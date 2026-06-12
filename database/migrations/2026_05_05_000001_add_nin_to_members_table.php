<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNinToMembersTable extends Migration
{
    /**
     * Run the migrations.
     * NIN is nullable so existing members are not broken.
     * Application logic enforces it as required for new members only.
     */
    public function up()
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('nin', 20)->nullable()->unique()->after('mobile')
                  ->comment('National Identification Number — unique across all branches');
        });
    }

    public function down()
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique(['nin']);
            $table->dropColumn('nin');
        });
    }
}