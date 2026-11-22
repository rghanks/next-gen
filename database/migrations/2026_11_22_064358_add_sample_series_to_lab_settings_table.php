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
            $table->string('sample_no_prefix', 20)->default('SMP')->after('show_qr_code');
            $table->unsignedSmallInteger('sample_no_digits')->default(6)->after('sample_no_prefix');
            $table->string('sample_no_format', 30)->default('prefix_padded')->after('sample_no_digits'); // 'prefix_padded', 'prefix_unpadded', 'numeric_only'
            $table->string('sample_no_reset_period', 20)->default('continuous')->after('sample_no_format'); // 'continuous', 'daily'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_settings', function (Blueprint $table) {
            $table->dropColumn([
                'sample_no_prefix',
                'sample_no_digits',
                'sample_no_format',
                'sample_no_reset_period',
            ]);
        });
    }
};
