<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('referring_doctors')) {
            Schema::create('referring_doctors', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150)->index();
                $table->string('mobile_no', 25)->nullable()->index();
                $table->string('degree', 100)->nullable();
                $table->string('hospital_name', 150)->nullable();
                $table->decimal('commission_percent', 5, 2)->default(0.00);
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
                $table->softDeletes();
            });

            // Seed default referrals
            $initialReferrals = [
                ['name' => 'Self', 'mobile_no' => null, 'created_at' => now(), 'updated_at' => now()],
              
            ];

            DB::table('referring_doctors')->insert($initialReferrals);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referring_doctors');
    }
};
