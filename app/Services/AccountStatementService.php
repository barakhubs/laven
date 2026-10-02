<?php

namespace App\Services;

use App\Models\SavingsAccount;
use Illuminate\Support\Facades\DB;

/**
 * Savings account statement rows with a running balance, shared by the
 * staff and client Account Statement reports. Plain SQL that runs on both
 * MySQL 8 and PostgreSQL.
 */
class AccountStatementService
{
    /**
     * Opening balance (completed transactions dated before $date1), then
     * every completed transaction from $date1 to $date2 in the order it
     * happened. Each row: trans_date, description, debit, credit, balance.
     */
    public static function rows(SavingsAccount $account, $date1, $date2): array
    {
        return DB::select("
            WITH opening AS (
                SELECT COALESCE(SUM(CASE WHEN dr_cr = 'cr' THEN amount ELSE -amount END), 0) AS balance
                FROM transactions
                WHERE member_id = ? AND savings_account_id = ? AND status = 2
                    AND date(trans_date) < CAST(? AS DATE)
            ),
            statement_lines AS (
                SELECT CAST(? AS DATE) AS trans_date, 0 AS sort_key, CAST(? AS DATE) AS happened_at, 0 AS id,
                    'Opening Balance' AS description, 0 AS debit, 0 AS credit
                UNION ALL
                SELECT date(trans_date), 1, trans_date, id,
                    COALESCE(NULLIF(description, ''), REPLACE(type, '_', ' ')),
                    CASE WHEN dr_cr = 'dr' THEN amount ELSE 0 END,
                    CASE WHEN dr_cr = 'cr' THEN amount ELSE 0 END
                FROM transactions
                WHERE member_id = ? AND savings_account_id = ? AND status = 2
                    AND date(trans_date) >= CAST(? AS DATE) AND date(trans_date) <= CAST(? AS DATE)
            )
            SELECT trans_date, description, debit, credit,
                (SELECT balance FROM opening)
                    + SUM(credit - debit) OVER (ORDER BY trans_date, sort_key, happened_at, id ROWS UNBOUNDED PRECEDING) AS balance
            FROM statement_lines
            ORDER BY trans_date, sort_key, happened_at, id
        ", [
            $account->member_id, $account->id, $date1,
            $date1, $date1,
            $account->member_id, $account->id, $date1, $date2,
        ]);
    }
}
