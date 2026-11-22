<?php

declare(strict_types=1);

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
        Schema::table('lab_settings', function (Blueprint $table) {
            $table->string('sample_no_prefix', 20)->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_settings', function (Blueprint $table) {
            $table->string('sample_no_prefix', 20)->default('SMP')->change();
        });
    }
};
