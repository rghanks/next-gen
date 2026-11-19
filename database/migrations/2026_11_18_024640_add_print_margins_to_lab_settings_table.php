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
            $table->unsignedSmallInteger('print_top_margin')->default(35)->after('footer_height');
            $table->unsignedSmallInteger('print_bottom_margin')->default(20)->after('print_top_margin');
            $table->boolean('print_with_header')->default(false)->after('print_bottom_margin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_settings', function (Blueprint $table) {
            $table->dropColumn(['print_top_margin', 'print_bottom_margin', 'print_with_header']);
        });
    }
};
