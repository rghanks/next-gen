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
        if (!Schema::hasTable('table_investigation_group_test_index')) {
            Schema::create('table_investigation_group_test_index', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->integer('group_id')->nullable();
                $table->integer('test_id')->nullable();
                $table->integer('sequence_no')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['group_id', 'test_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_investigation_group_test_index');
    }
};
