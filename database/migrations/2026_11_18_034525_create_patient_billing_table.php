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
        if (!Schema::hasTable('patient_billing')) {
            Schema::create('patient_billing', function (Blueprint $table) {
                $table->integer('id', true);
                $table->bigInteger('bill_number')->nullable();
                $table->integer('visit_id')->nullable()->index();
                $table->integer('i_cover')->nullable()->index();
                $table->integer('discount_auth_by')->nullable();
                $table->boolean('status')->default(true);
                $table->boolean('is_partially_settled')->default(false);
                $table->boolean('is_settled')->default(false);
                $table->decimal('discount', 10)->default(0);
                $table->decimal('discount_per', 10, 6)->nullable()->default(0);
                $table->string('discount_type', 191)->nullable();
                $table->string('discount_name', 191)->nullable();
                $table->boolean('is_open')->default(false);
                $table->boolean('is_cancelled')->nullable();
                $table->boolean('is_abdm_approved')->default(false)->index();
                $table->unsignedBigInteger('abdm_care_context_id')->nullable()->index();
                $table->integer('receipt_no')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->dateTime('bill_close_date')->nullable();
                $table->integer('created_by')->nullable();
                $table->integer('updated_by')->nullable();
                $table->double('disallowed_amt')->default(0);
                $table->string('claim_id', 191)->nullable();
                $table->string('attachment', 191)->nullable();
                $table->double('service_charge')->default(0);
                $table->string('remarks', 191)->nullable();
                $table->boolean('is_locked')->default(false);
                $table->integer('locked_by')->nullable();
                $table->timestamp('locked_at')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_billing');
    }
};
