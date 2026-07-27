<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLoanOfficerIdToMembersTable extends Migration
{
    /**
     * Run the migrations.
     * Tracks which staff member (loan officer) a client is attached to.
     * Nullable so existing clients are not broken; can be assigned later.
     */
    public function up()
    {
        Schema::table('members', function (Blueprint $table) {
            $table->unsignedBigInteger('loan_officer_id')->nullable()->after('created_user_id');
            $table->index('loan_officer_id');
        });
    }

    public function down()
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropIndex(['loan_officer_id']);
            $table->dropColumn('loan_officer_id');
        });
    }
}

