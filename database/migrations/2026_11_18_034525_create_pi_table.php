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
        if (!Schema::hasTable('pi')) {
            Schema::create('pi', function (Blueprint $table) {
                $table->bigInteger('id', true);
                $table->bigInteger('uhid');
                $table->string('uhid_prefix', 20)->nullable();
                $table->integer('pi_status')->default(0);
                $table->boolean('r_source')->default(false);
                $table->integer('i_cover');
                $table->string('policy_no', 191)->nullable();
                $table->string('email_id', 300)->nullable();
                $table->string('prefix', 15);
                $table->string('Fname', 30)->fulltext('fname');
                $table->string('Lname', 30)->nullable();
                $table->integer('age');
                $table->string('sex', 11);
                $table->string('vpo', 30);
                $table->integer('houseno');
                $table->string('street', 30);
                $table->string('city', 30);
                $table->string('district', 30);
                $table->string('state', 30);
                $table->string('login_id', 10);
                $table->bigInteger('mobile_no')->nullable();
                $table->string('country_code', 20)->nullable();
                $table->boolean('mnvs')->default(false);
                $table->integer('mnvs_otp');
                $table->dateTime('mnvs_otp_expiry')->nullable();
                $table->string('Fathername', 30)->nullable();
                $table->string('spousename', 30)->nullable();
                $table->string('guardian_name', 200)->nullable();
                $table->string('emergency_contact_name', 191)->nullable();
                $table->string('emergency_contact_relation', 191)->nullable();
                $table->string('emergency_contact_phone', 20)->nullable();
                $table->string('blood_group', 10)->nullable();
                $table->string('payer_name', 191)->nullable();
                $table->string('marital_status', 30)->nullable();
                $table->string('occupation', 100)->nullable();
                $table->string('id_type', 50)->nullable();
                $table->string('id_no', 100)->nullable();
                $table->integer('pin');
                $table->timestamp('timeentered')->useCurrent();
                $table->string('email_id1', 40);
                $table->string('email_id2', 40);
                $table->string('Mothername', 30);
                $table->string('Address', 100)->nullable();
                $table->integer('dump');
                $table->string('passcode', 30);
                $table->integer('user_p_level');
                $table->bigInteger('doctor_id');
                $table->string('org_name', 200);
                $table->integer('hospital_id');
                $table->string('opr_name', 100);
                $table->integer('opr_id');
                $table->string('mars_passcode', 10);
                $table->date('date');
                $table->string('mr_no', 20)->nullable();
                $table->integer('ipd_no');
                $table->time('time');
                $table->boolean('admission_status');
                $table->integer('m_count');
                $table->integer('p_lang');
                $table->date('dob')->nullable();
                $table->boolean('is_dob_calc')->default(false);
                $table->boolean('in_vac')->default(false);
                $table->string('lead_gen', 200)->nullable();
                $table->decimal('first_reg_charge', 10);
                $table->bigInteger('whats_app_no')->nullable();
                $table->boolean('whatsapp_verify')->default(false);
                $table->string('image', 191)->nullable();
                $table->integer('global_euid');
                $table->string('abha_add', 300);
                $table->string('abha_mobile', 20);
                $table->integer('is_registered')->default(0);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->string('referring_doctor', 50)->nullable();
                $table->integer('created_by')->nullable();
                $table->integer('updated_by')->nullable();
                $table->boolean('is_reserved')->default(false)->comment('Reserved for billing dept to hospital');
                $table->json('others')->nullable()->default('{"is_transfered_to_sms_server": 0}');
                $table->json('clinical_history')->nullable();
                $table->string('abha_number', 20)->nullable();
                $table->string('abha_address', 50)->nullable();
                $table->json('abha_address_list')->nullable();
                $table->boolean('abha_verified')->default(false);
                $table->boolean('abdm_enabled')->default(true);

                $table->unique(['hospital_id', 'uhid']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pi');
    }
};
