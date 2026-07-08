<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Grants the new 'reports.credit_score_report' permission to every role
     * that already has 'reports.loan_due_report', so existing staff roles
     * don't lose access to a report they could already see equivalents of.
     * New/other roles are unaffected and can be granted the permission
     * manually from Roles > Edit > Permissions.
     */
    public function up()
    {
        $roleIds = DB::table('permissions')
            ->where('permission', 'reports.loan_due_report')
            ->pluck('role_id');

        $now = now();

        foreach ($roleIds as $roleId) {
            $exists = DB::table('permissions')
                ->where('role_id', $roleId)
                ->where('permission', 'reports.credit_score_report')
                ->exists();

            if (!$exists) {
                DB::table('permissions')->insert([
                    'role_id'    => $roleId,
                    'permission' => 'reports.credit_score_report',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        DB::table('permissions')
            ->where('permission', 'reports.credit_score_report')
            ->delete();
    }
};

