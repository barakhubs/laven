<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Application + processing fees taken out of the payout on approval (the
 * client receives applied - 30% reserve - fees but still owes the full
 * applied amount). Kept on the loan, as charged, so revenue reports read
 * what was actually withheld even if the product's fee settings change.
 */
class AddFeeColumnsToLoansTable extends Migration
{
    public function up()
    {
        Schema::table('loans', function (Blueprint $table) {
            if (! Schema::hasColumn('loans', 'application_fee')) {
                $table->decimal('application_fee', 10, 2)->default(0);
            }
            if (! Schema::hasColumn('loans', 'processing_fee')) {
                $table->decimal('processing_fee', 10, 2)->default(0);
            }
        });
    }

    public function down()
    {
        Schema::table('loans', function (Blueprint $table) {
            foreach (['application_fee', 'processing_fee'] as $column) {
                if (Schema::hasColumn('loans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
