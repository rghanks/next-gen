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
        // 1. lab_bill_sequences (locked sequence generator)
        Schema::create('lab_bill_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 20)->default('BILL');
            $table->string('financial_year', 10);
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['prefix', 'financial_year'], 'uniq_seq_prefix_fy');
        });

        // 2. lab_price_lists & items
        Schema::create('lab_price_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('payer_category', 50)->default('general')->index(); // general, tpa, insurance, corporate
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('lab_price_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained('lab_price_lists')->cascadeOnDelete();
            $table->unsignedBigInteger('service_id')->index(); // hospital_services.id
            $table->decimal('charge', 12, 2)->default(0.00);
            $table->timestamps();

            $table->unique(['price_list_id', 'service_id'], 'uniq_price_list_service');
        });

        // 3. lab_orders
        Schema::create('lab_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('visit_id')->nullable()->index(); // visitlog.id
            $table->unsignedBigInteger('patient_id')->index(); // pi.id
            $table->unsignedBigInteger('bill_id')->nullable()->index(); // patient_billing.id
            $table->string('accession_no', 40)->unique();
            $table->string('client_token', 64)->nullable()->unique(); // idempotency token
            $table->string('priority', 20)->default('routine')->index(); // routine, stat, urgent
            $table->string('status', 30)->default('ordered')->index(); // ordered, collected, received, in_process, resulted, verified, released, cancelled
            $table->unsignedBigInteger('ordered_by')->nullable()->index();
            $table->unsignedBigInteger('branch_id')->default(1)->index();
            $table->text('clinical_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'branch_id'], 'idx_orders_status_branch');
            $table->index(['created_at', 'status'], 'idx_orders_created_status');
        });

        // 4. lab_order_items
        Schema::create('lab_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_order_id')->constrained('lab_orders')->cascadeOnDelete();
            $table->unsignedBigInteger('service_id')->index(); // hospital_services.id
            $table->unsignedBigInteger('gen_bill_id')->nullable()->index(); // patient_gen_bill.id
            $table->string('status', 30)->default('ordered')->index();
            $table->dateTime('tat_due_at')->nullable()->index();
            $table->boolean('is_outsource')->default(false)->index();
            $table->unsignedBigInteger('outsource_lab_id')->nullable()->index();
            $table->string('outsource_notes')->nullable();
            $table->string('outsource_report_path')->nullable();
            $table->unsignedInteger('row_version')->default(1);
            $table->timestamps();

            $table->index(['lab_order_id', 'status'], 'idx_order_items_order_status');
        });

        // 5. specimens
        Schema::create('specimens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_order_id')->constrained('lab_orders')->cascadeOnDelete();
            $table->string('barcode', 64)->unique();
            $table->string('specimen_type', 50)->default('blood')->index(); // blood, serum, plasma, urine, stool, csf, swab, tissue
            $table->string('container', 50)->default('edta_tube'); // edta, plain, citrate, fluoride, sterile_cup
            $table->dateTime('collected_at')->nullable();
            $table->unsignedBigInteger('collected_by')->nullable()->index();
            $table->dateTime('received_at')->nullable();
            $table->unsignedBigInteger('received_by')->nullable()->index();
            $table->string('condition', 30)->default('normal'); // normal, hemolyzed, lipemic, icteric, insufficient, clotted
            $table->text('reject_reason')->nullable();
            $table->timestamps();

            $table->index(['lab_order_id', 'specimen_type'], 'idx_specimens_order_type');
        });

        // 6. specimen_items (many-to-many)
        Schema::create('specimen_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('specimen_id')->constrained('specimens')->cascadeOnDelete();
            $table->foreignId('lab_order_item_id')->constrained('lab_order_items')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['specimen_id', 'lab_order_item_id'], 'uniq_specimen_order_item');
        });

        // 7. lab_parameters
        Schema::create('lab_parameters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_id')->index(); // hospital_services.id
            $table->string('name', 150);
            $table->string('short_code', 50)->index();
            $table->string('unit', 50)->nullable();
            $table->string('method', 100)->nullable();
            $table->string('loinc_code', 50)->nullable()->index();
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->string('input_type', 30)->default('numeric'); // numeric, text, options, culture
            $table->json('options')->nullable(); // dropdown or culture options
            $table->text('formula')->nullable(); // symfony expression e.g. "param_152 * 3"
            $table->integer('sort_order')->default(0)->index();
            $table->timestamps();

            $table->index(['service_id', 'sort_order'], 'idx_params_service_sort');
        });

        // 8. lab_reference_ranges
        Schema::create('lab_reference_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parameter_id')->constrained('lab_parameters')->cascadeOnDelete();
            $table->string('sex', 10)->default('both')->index(); // male, female, both
            $table->unsignedInteger('age_from_days')->default(0);
            $table->unsignedInteger('age_to_days')->default(43800); // up to 120 years
            $table->decimal('low', 12, 4)->nullable();
            $table->decimal('high', 12, 4)->nullable();
            $table->decimal('critical_low', 12, 4)->nullable();
            $table->decimal('critical_high', 12, 4)->nullable();
            $table->text('text_range')->nullable();
            $table->timestamps();

            $table->index(['parameter_id', 'sex', 'age_from_days', 'age_to_days'], 'idx_ref_range_lookup');
        });

        // 9. lab_result_values
        Schema::create('lab_result_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('lab_order_items')->cascadeOnDelete();
            $table->foreignId('parameter_id')->constrained('lab_parameters')->cascadeOnDelete();
            $table->text('value_text')->nullable();
            $table->decimal('value_numeric', 14, 4)->nullable();
            $table->string('unit', 50)->nullable();
            $table->decimal('ref_low', 12, 4)->nullable();
            $table->decimal('ref_high', 12, 4)->nullable();
            $table->text('ref_text')->nullable();
            $table->string('flag', 10)->default('N')->index(); // N (normal), L (low), H (high), LL (panic low), HH (panic high), A (abnormal)
            $table->unsignedBigInteger('entered_by')->nullable()->index();
            $table->dateTime('entered_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['order_item_id', 'parameter_id'], 'idx_results_item_param');
        });

        // 10. lab_critical_acknowledgements
        Schema::create('lab_critical_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_value_id')->constrained('lab_result_values')->cascadeOnDelete();
            $table->string('informed_person', 150);
            $table->unsignedBigInteger('informed_by')->index();
            $table->dateTime('informed_at');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['result_value_id', 'informed_at'], 'idx_critical_ack_lookup');
        });

        // 11. lab_reports
        Schema::create('lab_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_order_id')->constrained('lab_orders')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('pdf_path', 255)->nullable();
            $table->string('pdf_sha256', 64)->nullable();
            $table->string('template', 50)->default('standard'); // standard, letterhead, compact
            $table->unsignedBigInteger('verified_by')->nullable()->index();
            $table->dateTime('released_at')->nullable()->index();
            $table->unsignedBigInteger('amended_from_id')->nullable()->index();
            $table->text('amend_reason')->nullable();
            $table->timestamps();

            $table->unique(['lab_order_id', 'version'], 'uniq_order_report_version');
        });

        // 12. lab_order_events (append-only audit log)
        Schema::create('lab_order_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('order_item_id')->nullable()->index();
            $table->string('from_state', 30)->nullable();
            $table->string('to_state', 30);
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['order_id', 'created_at'], 'idx_order_events_order_time');
        });

        // 13. lab_discount_approvals
        Schema::create('lab_discount_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bill_id')->index();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->unsignedBigInteger('item_id')->nullable()->index();
            $table->decimal('discount_amount', 12, 2)->default(0.00);
            $table->decimal('discount_percent', 5, 2)->default(0.00);
            $table->string('reason', 255);
            $table->unsignedBigInteger('requested_by')->index();
            $table->unsignedBigInteger('approved_by')->nullable()->index();
            $table->string('status', 20)->default('approved')->index(); // pending, approved, rejected
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
        });

        // 14. lab_bill_cancellations
        Schema::create('lab_bill_cancellations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bill_id')->index();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->string('credit_note_no', 50)->unique();
            $table->string('reason', 255);
            $table->unsignedBigInteger('approved_by')->index();
            $table->string('refund_mode', 30)->default('cash'); // cash, card, upi, bank_transfer, credit_note
            $table->decimal('refund_amount', 12, 2)->default(0.00);
            $table->timestamp('cancelled_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_bill_cancellations');
        Schema::dropIfExists('lab_discount_approvals');
        Schema::dropIfExists('lab_order_events');
        Schema::dropIfExists('lab_reports');
        Schema::dropIfExists('lab_critical_acknowledgements');
        Schema::dropIfExists('lab_result_values');
        Schema::dropIfExists('lab_reference_ranges');
        Schema::dropIfExists('lab_parameters');
        Schema::dropIfExists('specimen_items');
        Schema::dropIfExists('specimens');
        Schema::dropIfExists('lab_order_items');
        Schema::dropIfExists('lab_orders');
        Schema::dropIfExists('lab_price_list_items');
        Schema::dropIfExists('lab_price_lists');
        Schema::dropIfExists('lab_bill_sequences');
    }
};
