<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmailSmsTemplatesTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('email_sms_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('slug', 50);
            $table->string('subject');
            $table->text('email_body')->nullable();
            $table->text('sms_body')->nullable();
            $table->text('notification_body')->nullable();
            $table->text('shortcode')->nullable();
            $table->tinyInteger('email_status')->default(0);
            $table->tinyInteger('sms_status')->default(0);
            $table->tinyInteger('notification_status')->default(0);
            $table->tinyInteger('template_mode')->default(0)->comment('0 = all, 1 = email, 2 = sms, 3 = notification');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('email_sms_templates');
    }
}
