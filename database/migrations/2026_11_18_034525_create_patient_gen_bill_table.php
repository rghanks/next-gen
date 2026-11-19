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
        if (!Schema::hasTable('patient_gen_bill')) {
            Schema::create('patient_gen_bill', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('bill_id')->index();
                $table->integer('service_id')->index();
                $table->decimal('charges', 10);
                $table->decimal('qty');
                $table->decimal('cgst', 10)->default(0);
                $table->decimal('sgst', 10)->default(0);
                $table->decimal('igst', 10)->default(0);
                $table->decimal('cost', 10)->nullable();
                $table->unsignedBigInteger('outsource_lab_id')->nullable();
                $table->decimal('discount', 10)->default(0);
                $table->decimal('discount_per', 10, 6)->nullable()->default(0);
                $table->integer('discount_auth_by')->nullable();
                $table->dateTime('service_delivered_at')->nullable();
                $table->json('additional_info')->nullable();
                $table->enum('service_status', ['booked', 'arrived', 'on_going', 'completed', 'cancelled'])->default('booked');
                $table->boolean('is_abdm_approved')->default(false)->index();
                $table->boolean('is_cancelled')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->integer('cancelled_by')->nullable();
                $table->tinyInteger('is_deleted')->default(0);
                $table->timestamp('created_at')->useCurrent()->index();
                $table->timestamp('updated_at')->useCurrent();
                $table->integer('created_by')->nullable();
                $table->integer('updated_by')->nullable();
                $table->string('sub_bill_id', 191)->nullable();
                $table->boolean('is_settled')->default(false);
                $table->integer('against_bill_id')->nullable();
                $table->dateTime('settled_on')->nullable();
                $table->integer('settled_by')->nullable();
                $table->integer('payment_id')->nullable();
                $table->boolean('is_er')->default(false);
                $table->integer('doctor_id');
                $table->integer('location_id')->nullable();
                $table->integer('i_cover');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_gen_bill');
    }
};
