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
            $table->unsignedBigInteger('starting_sample_no')->nullable()->after('sample_no_prefix');
            $table->boolean('is_sample_series_locked')->default(false)->after('starting_sample_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_settings', function (Blueprint $table) {
            $table->dropColumn(['starting_sample_no', 'is_sample_series_locked']);
        });
    }
};
