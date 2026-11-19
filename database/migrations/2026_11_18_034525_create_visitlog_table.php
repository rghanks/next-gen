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
        if (!Schema::hasTable('visitlog')) {
            Schema::create('visitlog', function (Blueprint $table) {
                $table->integer('id', true);
                $table->dateTime('ad2')->nullable();
                $table->dateTime('dd2')->nullable();
                $table->integer('source')->default(1);
                $table->integer('pres_status')->default(0);
                $table->integer('app_sms_count')->nullable()->default(0);
                $table->dateTime('fud')->nullable();
                $table->integer('fud_status')->default(0);
                $table->integer('visit_clinical_status')->nullable();
                $table->time('admission_time')->nullable();
                $table->time('discharge_time')->nullable();
                $table->integer('patient_id');
                $table->integer('visit_id')->nullable();
                $table->text('event')->nullable();
                $table->timestamp('date')->useCurrent();
                $table->integer('doctor_id');
                $table->integer('hospital_id');
                $table->date('discharge_date')->nullable();
                $table->boolean('visit_status')->nullable()->default(false);
                $table->string('discharge_type', 191)->nullable();
                $table->date('admission_date')->nullable();
                $table->boolean('discharged')->nullable();
                $table->time('visit_time')->nullable();
                $table->string('sms_d_key', 50)->nullable();
                $table->integer('sms_d_count')->nullable();
                $table->integer('payment_status')->default(1);
                $table->integer('opd_visit_type')->default(1);
                $table->integer('edit_lock')->default(0);
                $table->decimal('ch', 10);
                $table->decimal('epi_gfr', 10)->nullable();
                $table->decimal('creat', 10)->nullable();
                $table->integer('bed_id')->nullable();
                $table->decimal('v_dis', 10)->nullable();
                $table->decimal('other_ch', 10)->nullable();
                $table->decimal('procedure_ch', 13)->nullable();
                $table->decimal('reg_ch', 13)->nullable();
                $table->decimal('oper_ch', 13)->nullable();
                $table->decimal('anaes_ch', 13)->nullable();
                $table->decimal('ot_assis_surg_ch', 13)->nullable();
                $table->decimal('ot_ch', 13)->nullable();
                $table->decimal('med_ch', 13)->nullable();
                $table->decimal('implant_ch', 13)->nullable();
                $table->string('implant_desc', 300)->nullable();
                $table->string('procedure_desc', 300)->nullable();
                $table->decimal('ambu_ch', 13)->nullable();
                $table->boolean('close_for_next_visit')->default(false);
                $table->time('at1')->nullable();
                $table->time('dt1')->nullable();
                $table->dateTime('surgery_time')->nullable();
                $table->string('obs_booked', 100)->nullable();
                $table->string('obs_immunized', 100)->nullable();
                $table->string('hb', 20)->nullable();
                $table->string('tlc', 20)->nullable();
                $table->string('dlc', 100)->nullable();
                $table->string('plat_count', 30)->nullable();
                $table->string('pti', 20)->nullable();
                $table->string('gct', 100)->nullable();
                $table->string('abo_rh', 50)->nullable();
                $table->string('hba1c', 20)->nullable();
                $table->string('lft', 200)->nullable();
                $table->string('serology', 100)->nullable();
                $table->string('vdrl', 100)->nullable();
                $table->string('rft', 200)->nullable();
                $table->string('urine_exam', 200)->nullable();
                $table->string('tsh', 200)->nullable();
                $table->decimal('tpa_pay', 10)->default(0);
                $table->string('other_ch_des', 200)->nullable();
                $table->integer('is_active')->default(0);
                $table->decimal('cash_pay', 10)->default(0);
                $table->decimal('card_pay', 10)->default(0);
                $table->decimal('online_pay', 10)->default(0);
                $table->boolean('is_template')->default(false);
                $table->string('t_name', 300)->nullable();
                $table->boolean('social_media_dis_bool')->default(false);
                $table->string('vital_comment', 300)->nullable();
                $table->integer('queue_status')->default(0);
                $table->integer('token_no')->nullable();
                $table->bigInteger('last_update')->nullable();
                $table->boolean('ready_to_upload')->default(false);
                $table->boolean('upload_confirm_status')->default(false);
                $table->bigInteger('last_upload_call_time')->nullable();
                $table->string('last_upload_call_status', 2)->default('');
                $table->boolean('feed_back_sms_done')->default(true);
                $table->integer('is_visited')->default(0);
                $table->boolean('on_hold')->default(false);
                $table->text('advice')->nullable();
                $table->string('remarks', 191)->nullable();
                $table->date('next_visit_date')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->integer('created_by')->nullable();
                $table->integer('updated_by')->nullable();
                $table->integer('payment_receipt_no')->nullable();
                $table->string('visit_type', 191)->nullable();
                $table->boolean('emergency')->default(false);
                $table->integer('er_status')->default(0);
                $table->boolean('abdm_enabled')->default(true);
                $table->json('visit_upload_data')->nullable();
                $table->json('others')->nullable()->default('{"is_followup_transfered_to_sms_server": 0, "is_discharge_transfered_to_sms_server": 0}');
                $table->string('ref_by', 191)->nullable();
                $table->boolean('is_cancelled')->default(false);
                $table->integer('discharge_by')->nullable();
                $table->integer('team_id')->nullable();
                $table->integer('ds_initiated')->nullable();
                $table->dateTime('ds_initiated_at')->nullable();
                $table->integer('ds_initiated_by')->nullable();
                $table->boolean('is_base')->default(false);
                $table->dateTime('er_discharge_date')->nullable();

                $table->index(['hospital_id', 'date', 'visit_status', 'is_cancelled'], 'all_where_condition_index');
                $table->index(['patient_id', 'doctor_id', 'hospital_id', 'visit_id'], 'pid3');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitlog');
    }
};
