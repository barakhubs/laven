<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuditLogsTable extends Migration
{
    public function up()
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Who did it
            $table->bigInteger('user_id')->nullable()->index();
            $table->string('user_name', 191)->nullable();       // snapshot in case user is deleted
            $table->string('user_type', 50)->nullable();        // superadmin, admin, user, customer

            // What they did
            $table->string('action', 50)->index();              // created, updated, deleted, approved, rejected, login, logout, blocked
            $table->string('module', 100)->index();             // Member, Loan, Transaction, etc.
            $table->bigInteger('record_id')->nullable()->index(); // the affected record's id
            $table->string('record_label', 191)->nullable();    // human-readable label e.g. "Member: John Doe"

            // Detail
            $table->json('old_values')->nullable();             // before state
            $table->json('new_values')->nullable();             // after state
            $table->text('description')->nullable();            // plain-English description
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('url', 500)->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('audit_logs');
    }
}