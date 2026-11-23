<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_settings', function (Blueprint $table) {
            $table->boolean('print_with_signature')->default(true)->after('show_qr_code');
            $table->string('signature_position', 30)->default('bottom_of_page')->after('print_with_signature'); // 'bottom_of_page' | 'after_report'
            $table->string('signature_page_target', 30)->default('all_pages')->after('signature_position'); // 'all_pages' | 'last_page_only'
            $table->string('signature_layout', 30)->default('dual')->after('signature_page_target'); // 'dual' | 'right_only' | 'left_only'
            $table->string('technician_signature_label', 100)->nullable()->default('Prepared By')->after('technician_degree');
            $table->string('pathologist_signature_label', 100)->nullable()->default('Verified By')->after('pathologist_degree');
            $table->unsignedSmallInteger('signature_height')->default(36)->after('signature_layout');
            $table->unsignedSmallInteger('signature_space_top')->default(5)->after('signature_height'); // mm
            $table->unsignedSmallInteger('signature_space_bottom')->default(5)->after('signature_space_top'); // mm
            $table->unsignedSmallInteger('signature_space_left')->default(10)->after('signature_space_bottom'); // mm
            $table->unsignedSmallInteger('signature_space_right')->default(10)->after('signature_space_left'); // mm
        });
    }

    public function down(): void
    {
        Schema::table('lab_settings', function (Blueprint $table) {
            $table->dropColumn([
                'print_with_signature',
                'signature_position',
                'signature_page_target',
                'signature_layout',
                'technician_signature_label',
                'pathologist_signature_label',
                'signature_height',
                'signature_space_top',
                'signature_space_bottom',
                'signature_space_left',
                'signature_space_right',
            ]);
        });
    }
};
