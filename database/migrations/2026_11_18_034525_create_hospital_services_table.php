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
        if (!Schema::hasTable('hospital_services')) {
            Schema::create('hospital_services', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('spec_id');
                $table->string('service', 500)->unique('hospital_service_name_unique');
                $table->string('display_full_name', 191)->nullable();
                $table->string('comment', 100);
                $table->mediumText('brief_des');
                $table->integer('active')->default(1);
                $table->string('flag')->nullable();
                $table->string('sub_flag', 191)->nullable();
                $table->integer('category_id')->default(0);
                $table->boolean('is_group')->default(false);
                $table->boolean('is_reserved')->default(false);
                $table->boolean('is_subgroup')->default(false);
                $table->string('template', 191)->nullable();
                $table->string('short_code', 20)->nullable();
                $table->string('code', 191)->nullable();
                $table->string('loinc_code', 191)->nullable();
                $table->string('universal_code', 191)->nullable();
                $table->text('input_attributes')->nullable();
                $table->json('group_attributes')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->boolean('bifurcation_allow')->default(false);
                $table->json('supporting_docs')->nullable();
                $table->string('ledger_key', 191)->nullable();
                $table->boolean('is_surgical')->default(false);

                $table->unique(['service', 'brief_des']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hospital_services');
    }
};
