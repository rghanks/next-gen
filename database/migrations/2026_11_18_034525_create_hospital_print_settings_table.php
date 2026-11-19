<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('hospital_print_settings')) {
            Schema::create('hospital_print_settings', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->integer('hospital_id');
                $table->text('visit_slip')->nullable();
                $table->text('lab_investigation')->nullable();
                $table->longText('radiology')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hospital_print_settings');
    }
};
