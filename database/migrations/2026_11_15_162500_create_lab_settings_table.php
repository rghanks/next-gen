<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_settings', function (Blueprint $table) {
            $table->id();
            $table->string('lab_name', 191)->default('Central Hospital Laboratory');
            $table->string('tagline', 191)->nullable()->default('Department of Pathology & Clinical Diagnostics');
            $table->string('accreditation_text', 255)->nullable()->default('Fully Automated Computerized Diagnostic Lab • Accredited Quality Care');
            $table->string('gst_number', 50)->nullable();
            $table->string('reg_number', 50)->nullable();
            $table->string('mobile_no', 30)->nullable()->default('9416204655');
            $table->string('phone_alt', 30)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('website', 150)->nullable();
            $table->string('address_line1', 255)->nullable()->default('Main Hospital Road');
            $table->string('address_line2', 255)->nullable()->default('City Center, Haryana - 125001');
            
            // Visuals
            $table->string('logo_path', 255)->nullable();
            $table->string('header_image_path', 255)->nullable();
            $table->string('footer_image_path', 255)->nullable();
            $table->boolean('use_letterhead_image')->default(false);
            $table->unsignedSmallInteger('header_height')->default(120);
            $table->unsignedSmallInteger('footer_height')->default(50);

            // Signatures
            $table->string('technician_name', 100)->default('Anita');
            $table->string('technician_designation', 100)->default('Lab Technician');
            $table->string('technician_degree', 100)->nullable();
            $table->string('technician_signature_path', 255)->nullable()->default('assets/files/signatures/sign_anita.png');

            $table->string('pathologist_name', 100)->default('Dr. Sonali Kalyan');
            $table->string('pathologist_designation', 100)->default('Consultant Pathologist');
            $table->string('pathologist_degree', 100)->nullable()->default('M.B.B.S., MD (Path)');
            $table->string('pathologist_signature_path', 255)->nullable()->default('assets/files/signatures/sign_sonali.png');

            // Print toggles & notes
            $table->boolean('show_barcode')->default(true);
            $table->boolean('show_qr_code')->default(true);
            $table->text('report_disclaimer')->nullable();
            $table->text('receipt_footer_note')->nullable();

            $table->timestamps();
        });

        // Seed initial default record
        DB::table('lab_settings')->insert([
            'lab_name' => 'Central Hospital Laboratory',
            'tagline' => 'Department of Pathology & Clinical Diagnostics',
            'accreditation_text' => 'Fully Automated Computerized Diagnostic Lab • Accredited Quality Care',
            'gst_number' => '06AAAAA0000A1Z5',
            'reg_number' => 'LAB/HAR/2026/884',
            'mobile_no' => '9416204655',
            'phone_alt' => '01662-234567',
            'email' => 'lab@centralhospital.local',
            'website' => 'https://centralhospital.local',
            'address_line1' => 'Opp. Civil Hospital, Hospital Road',
            'address_line2' => 'Hisar, Haryana - 125001',
            'technician_name' => 'Anita',
            'technician_designation' => 'Lab Technician',
            'technician_degree' => 'DMLT',
            'technician_signature_path' => 'assets/files/signatures/sign_anita.png',
            'pathologist_name' => 'Dr. Sonali Kalyan',
            'pathologist_designation' => 'Consultant Pathologist',
            'pathologist_degree' => 'M.B.B.S., MD (Path)',
            'pathologist_signature_path' => 'assets/files/signatures/sign_sonali.png',
            'show_barcode' => true,
            'show_qr_code' => true,
            'report_disclaimer' => '* Partial results are not intended for medico-legal purposes. Clinical correlation advised.',
            'receipt_footer_note' => 'Thank you for choosing our diagnostics services. Get well soon!',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_settings');
    }
};
