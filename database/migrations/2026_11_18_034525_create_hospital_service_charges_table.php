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
        if (!Schema::hasTable('hospital_service_charges')) {
            Schema::create('hospital_service_charges', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('hospital_id')->nullable();
                $table->integer('insurance')->nullable();
                $table->integer('service')->nullable();
                $table->text('charges')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->boolean('is_editable')->default(false);
                $table->decimal('opd', 10)->nullable();
                $table->decimal('er_opd', 10)->nullable();
                $table->decimal('location_1', 10)->nullable();
                $table->decimal('er_location_1', 10)->nullable();
                $table->decimal('location_2', 10)->nullable();
                $table->decimal('er_location_2', 10)->nullable();
                $table->decimal('location_3', 10)->nullable();
                $table->decimal('er_location_3', 10)->nullable();
                $table->decimal('location_4', 10)->nullable();
                $table->decimal('er_location_4', 10)->nullable();
                $table->decimal('location_5', 10)->nullable();
                $table->decimal('er_location_5', 10)->nullable();
                $table->decimal('location_6', 10)->nullable();
                $table->decimal('er_location_6', 10)->nullable();
                $table->decimal('location_7', 10)->nullable();
                $table->decimal('er_location_7', 10)->nullable();
                $table->decimal('location_8', 10)->nullable();
                $table->decimal('er_location_8', 10)->nullable();
                $table->decimal('location_9', 10)->nullable();
                $table->decimal('er_location_9', 10)->nullable();

                $table->unique(['hospital_id', 'insurance', 'service'], 'hospital_service_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hospital_service_charges');
    }
};
