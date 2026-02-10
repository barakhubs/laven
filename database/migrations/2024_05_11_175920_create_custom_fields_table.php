<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('field_name', 191);
            $table->string('field_type', 20);
            $table->text('default_value')->nullable();
            $table->string('field_width', 30);
            $table->integer('max_size')->nullable();
            $table->string('is_required', 191)->default('nullable');
            $table->string('table', 30);
            $table->tinyInteger('allow_for_signup')->default(0);
            $table->tinyInteger('allow_to_list_view')->default(0);
            $table->tinyInteger('status')->default(0);
            $table->bigInteger('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('custom_fields');
    }
};
