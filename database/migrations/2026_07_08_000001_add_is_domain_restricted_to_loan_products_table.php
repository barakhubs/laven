<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks a loan product as "domain restricted" — i.e. it should only be
     * visible/reportable when the request is on the configured emergency
     * subdomain (config('app.emergency_domain')), and hidden from the main
     * domain everywhere loans are listed, reported on, or aggregated.
     *
     * This does NOT affect background jobs / cron (e.g. credit score
     * recalculation) — those must keep processing every loan regardless of
     * this flag. Domain scoping is applied at the HTTP request layer only.
     */
    public function up()
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->boolean('is_domain_restricted')->default(false)->after('status');
        });
    }

    public function down()
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->dropColumn('is_domain_restricted');
        });
    }
};

