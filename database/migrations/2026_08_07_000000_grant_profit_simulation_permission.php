<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Grants 'reports.profit_simulation' to every role that already has
     * 'reports.financial_summary', so existing staff don't lose access to
     * a report they could already see the equivalent of.
     */
    public function up()
    {
        $roleIds = DB::table('permissions')
            ->where('permission', 'reports.financial_summary')
            ->pluck('role_id');

        $now = now();

        foreach ($roleIds as $roleId) {
            $exists = DB::table('permissions')
                ->where('role_id', $roleId)
                ->where('permission', 'reports.profit_simulation')
                ->exists();

            if (!$exists) {
                DB::table('permissions')->insert([
                    'role_id'    => $roleId,
                    'permission' => 'reports.profit_simulation',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down()
    {
        DB::table('permissions')
            ->where('permission', 'reports.profit_simulation')
            ->delete();
    }
};