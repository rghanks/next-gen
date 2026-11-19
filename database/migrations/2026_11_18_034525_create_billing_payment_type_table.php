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
        if (!Schema::hasTable('billing_payment_type')) {
            Schema::create('billing_payment_type', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('bill_id')->nullable()->index();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->integer('created_by')->nullable();
                $table->integer('updated_by')->nullable();
                $table->decimal('amount', 10)->default(0);
                $table->decimal('tds', 10)->nullable();
                $table->string('payment_mode', 191)->nullable();
                $table->boolean('transaction_type')->default(false)->comment('0-Credit,1-Debit');
                $table->string('remarks', 191)->nullable();
                $table->string('transaction_id', 191)->nullable();
                $table->boolean('sub_bill_id')->nullable();
                $table->boolean('is_deleted')->default(false);
                $table->timestamp('payment_date')->useCurrent();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_payment_type');
    }
};
