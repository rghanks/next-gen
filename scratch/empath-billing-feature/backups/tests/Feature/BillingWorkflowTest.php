<?php

namespace Tests\Feature;

use App\Cache\CacheManager;
use App\Http\Controllers\V2\BillingWorkflowController;
use App\Http\Controllers\admin\PatientController;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class BillingWorkflowTest extends TestCase
{
    private array $permissions = ['patient', 'opd_visits', 'opd_billing', 'opd_discount'];
    private bool $referralsEnabled = false;
    private $modelEvents;

    public function createApplication()
    {
        $basePath = dirname((new \ReflectionClass(TestCase::class))->getFileName(), 2);
        $app = require $basePath . '/bootstrap/app.php';
        $app->afterBootstrapping(LoadConfiguration::class, function ($app) {
            $app['config']->set('database.default', 'sqlite');
            $app['config']->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
            $app['config']->set('cache.default', 'array');
            $app['config']->set('cache.stores.file', ['driver' => 'array']);
            $app['config']->set('session.driver', 'array');
            $app['config']->set('queue.default', 'sync');
            $app['config']->set('logging.default', 'errorlog');
            $app['config']->set('audit.enabled', false);
            $compiledViews = sys_get_temp_dir() . '/empath-billing-workflow-views';
            if (!is_dir($compiledViews)) mkdir($compiledViews, 0777, true);
            $app['config']->set('view.compiled', $compiledViews);
        });
        $app->make(Kernel::class)->bootstrap();
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->modelEvents = Model::getEventDispatcher();
        Model::unsetEventDispatcher();
        $user = Mockery::mock(User::class)->makePartial();
        $user->setRawAttributes(['id' => 9, 'org_id' => 1, 'is_superadmin' => 0]);
        $user->shouldReceive('can')->andReturnUsing(function ($ability, $args) { return in_array($args[0], $this->permissions, true); });
        $user->shouldReceive('hasRole')->andReturn(false);
        $this->actingAs($user);
        $this->mock(CacheManager::class, function ($mock) {
            $mock->shouldReceive('remember')->andReturnUsing(function ($key) {
                if (str_ends_with($key, ':doctors')) return collect([2 => (object) ['id' => 2, 'name' => 'Test Doctor']]);
                if (str_ends_with($key, ':insurances')) return collect([1 => (object) ['id' => 1, 'insurance_agen' => 'Cash']]);
                if (str_starts_with($key, 'hospital:settings:')) return collect(['refered_by_in_visit' => (object) ['status' => $this->referralsEnabled ? 1 : 0]]);
                return collect();
            });
        });
        Schema::create('pi', function (Blueprint $table) {
            $table->id(); $table->integer('hospital_id'); $table->integer('i_cover')->default(1);
            $table->boolean('is_registered')->default(false); $table->decimal('first_reg_charge')->default(100);
            $table->string('Fname')->default('Test'); $table->string('Lname')->default('Patient');
        });
        Schema::create('visitlog', function (Blueprint $table) {
            $table->id(); $table->integer('hospital_id'); $table->integer('patient_id'); $table->integer('doctor_id');
            $table->integer('visit_id')->default(1); $table->string('visit_type')->nullable();
            $table->integer('ref_by')->nullable(); $table->decimal('ch')->default(0);
            $table->integer('visit_status')->default(0); $table->boolean('emergency')->default(false);
            $table->boolean('is_visited')->default(false); $table->integer('created_by')->nullable();
            $table->string('date')->nullable(); $table->string('visit_time')->nullable(); $table->string('last_update')->nullable();
            $table->timestamps();
        });
        Schema::create('gen_pres_log_1', function (Blueprint $table) { $table->id(); $table->integer('visit_table_id'); });
        Schema::create('ref_users', function (Blueprint $table) { $table->id(); $table->integer('hospital_id'); $table->boolean('status')->default(true); });
        Schema::create('patient_billing', function (Blueprint $table) {
            $table->id(); $table->integer('visit_id'); $table->integer('status')->default(0);
            $table->boolean('is_cancelled')->default(false); $table->timestamps();
        });
        DB::table('pi')->insert([['id' => 10, 'hospital_id' => 1], ['id' => 20, 'hospital_id' => 2]]);
        Route::post('/__billing/register', [BillingWorkflowController::class, 'registerPatient']);
        Route::post('/__billing/visit', [BillingWorkflowController::class, 'prepareVisit']);
        Route::post('/__billing/bill', [BillingWorkflowController::class, 'saveBill']);
    }

    protected function tearDown(): void
    {
        Model::setEventDispatcher($this->modelEvents);
        parent::tearDown();
    }

    private function visitPayload(array $overrides = []): array
    {
        return array_replace(['patient_id' => 10, 'doctor_id' => 2, 'doctor_charges' => 300, 'add_visit' => 1], $overrides);
    }

    private function legacy(): PatientController
    {
        $legacy = Mockery::mock(PatientController::class)->makePartial();
        $legacy->shouldReceive('copy_old_visit_data_to_new')->andReturnNull();
        $this->app->instance(PatientController::class, $legacy);
        return $legacy;
    }

    private function existingVisit(array $overrides = []): int
    {
        return DB::table('visitlog')->insertGetId(array_replace([
            'hospital_id' => 1, 'patient_id' => 10, 'doctor_id' => 2, 'visit_type' => null,
            'created_at' => now(), 'updated_at' => now(),
        ], $overrides));
    }

    private function billPayload(int $visitId, array $overrides = []): array
    {
        return array_replace([
            'patient_id' => 10, 'doctor_id' => 2, 'prepared_visit_id' => $visitId,
            'request_id' => (string) Str::uuid(), 'service_id' => [5], 'charges' => [300],
            'qty' => [1], 'payment' => ['cash' => 300],
        ], $overrides);
    }

    public function test_visit_is_persisted_before_billing_and_retry_reuses_it(): void
    {
        $this->legacy();
        $first = $this->postJson('/__billing/visit', $this->visitPayload())->assertOk()->assertJson(['success' => true, 'registration_charge' => 100]);
        $second = $this->postJson('/__billing/visit', $this->visitPayload())->assertOk();
        $this->assertSame($first->json('visit_id'), $second->json('visit_id'));
        $this->assertDatabaseCount('visitlog', 1);
        $this->assertDatabaseCount('gen_pres_log_1', 1);
        $this->assertDatabaseCount('patient_billing', 0);
    }

    public function test_services_only_and_clinical_visits_keep_distinct_encounters(): void
    {
        $this->legacy();
        $nonClinicalId = $this->existingVisit(['visit_type' => 'non-clinical']);
        $clinical = $this->postJson('/__billing/visit', $this->visitPayload())->assertOk();
        $this->assertNotSame($nonClinicalId, $clinical->json('visit_id'));
        $this->postJson('/__billing/visit', $this->visitPayload(['add_visit' => 0]))->assertOk()->assertJson(['visit_id' => $nonClinicalId]);
        $this->assertDatabaseCount('visitlog', 2);
    }

    public function test_existing_patient_does_not_incur_a_registration_fee(): void
    {
        DB::table('pi')->where('id', 10)->update(['is_registered' => 1]);
        $this->existingVisit();
        $this->postJson('/__billing/visit', $this->visitPayload())->assertOk()->assertJson(['registration_charge' => 0]);
    }

    public function test_draft_lookup_is_scoped_to_the_prepared_visit(): void
    {
        $other = $this->existingVisit(['visit_type' => 'non-clinical']);
        $selected = $this->existingVisit();
        DB::table('patient_billing')->insert([['id' => 30, 'visit_id' => $other], ['id' => 31, 'visit_id' => $selected]]);
        $this->postJson('/__billing/visit', $this->visitPayload())->assertOk()->assertJson(['visit_id' => $selected, 'draft_bill_id' => 31]);
    }

    public function test_patient_doctor_and_visit_context_are_validated(): void
    {
        $this->postJson('/__billing/visit', $this->visitPayload(['patient_id' => 20]))->assertNotFound();
        $this->postJson('/__billing/visit', $this->visitPayload(['doctor_id' => 999]))->assertUnprocessable()->assertJsonValidationErrors('doctor_id');
        $foreignVisit = $this->existingVisit(['hospital_id' => 2, 'patient_id' => 20]);
        $this->postJson('/__billing/visit', $this->visitPayload(['visit_id' => $foreignVisit]))->assertUnprocessable()->assertJsonValidationErrors('visit_id');
    }

    public function test_billing_permission_does_not_grant_registration_or_visit_permission(): void
    {
        $this->permissions = ['opd_billing'];
        $this->postJson('/__billing/visit', $this->visitPayload())->assertForbidden();
        $this->postJson('/__billing/register', [])->assertForbidden();
    }

    public function test_registration_retries_do_not_create_another_patient(): void
    {
        $legacy = $this->legacy();
        $legacy->shouldReceive('save')->once()->andReturnUsing(function () {
            return response()->json(['success' => true, 'patient_id' => 11, 'patient' => ['id' => 11]]);
        });
        $payload = ['request_id' => (string) Str::uuid(), 'prefix' => 'Mr.', 'Fname' => 'Test', 'Lname' => 'Patient', 'mobile_no' => '9999900010', 'age' => 30, 'gender' => 'Male', 'i_cover' => 1];
        $this->postJson('/__billing/register', $payload)->assertOk()->assertJson(['patient_id' => 11]);
        $this->postJson('/__billing/register', $payload)->assertOk()->assertJson(['patient_id' => 11]);
        $this->postJson('/__billing/register', array_replace($payload, ['Fname' => 'Changed']))->assertConflict();
    }

    public function test_registration_cannot_modify_an_existing_patient_or_use_another_hospital_category(): void
    {
        $payload = ['request_id' => (string) Str::uuid(), 'prefix' => 'Mr.', 'Fname' => 'Test', 'mobile_no' => '9999900010', 'age' => 30, 'gender' => 'Male', 'i_cover' => 999];
        $this->postJson('/__billing/register', $payload)->assertUnprocessable()->assertJsonValidationErrors('i_cover');
        $this->postJson('/__billing/register', array_replace($payload, ['patient_id' => 10]))->assertUnprocessable()->assertJsonValidationErrors('patient_id');
    }

    public function test_bill_retries_preserve_the_exact_visit_and_do_not_collect_twice(): void
    {
        $visitId = $this->existingVisit();
        $legacy = $this->legacy();
        $legacy->shouldReceive('gen_opd_bill')->once()->andReturnUsing(function ($request) use ($visitId) {
            $this->assertSame($visitId, $request->prepared_visit_id);
            $id = DB::table('patient_billing')->insertGetId(['visit_id' => $visitId, 'status' => 1]);
            return response()->json(['success' => true, 'bill_id' => $id]);
        });
        $payload = $this->billPayload($visitId);
        $first = $this->postJson('/__billing/bill', $payload)->assertOk();
        $this->postJson('/__billing/bill', $payload)->assertOk()->assertJson(['bill_id' => $first->json('bill_id')]);
        $this->assertDatabaseCount('patient_billing', 1);
        $this->assertDatabaseCount('visitlog', 1);
    }

    public function test_bill_rejects_a_foreign_visit_or_a_bill_for_another_encounter(): void
    {
        $selected = $this->existingVisit();
        $foreign = $this->existingVisit(['hospital_id' => 2, 'patient_id' => 20]);
        DB::table('patient_billing')->insert(['id' => 40, 'visit_id' => $foreign]);
        $this->postJson('/__billing/bill', $this->billPayload($foreign))->assertNotFound();
        $this->postJson('/__billing/bill', $this->billPayload($selected, ['bill_no' => 40]))->assertNotFound();
    }

    public function test_finalizing_an_already_generated_draft_does_not_repeat_payment(): void
    {
        $visitId = $this->existingVisit();
        DB::table('patient_billing')->insert(['id' => 41, 'visit_id' => $visitId, 'status' => 1]);
        $this->legacy()->shouldNotReceive('gen_opd_bill');
        $this->postJson('/__billing/bill', $this->billPayload($visitId, ['bill_no' => 41, 'action2' => 'gen_bill_from_draft']))->assertOk()->assertJson(['bill_id' => 41]);
    }

    public function test_an_unloaded_draft_must_be_resumed_before_saving(): void
    {
        $visitId = $this->existingVisit();
        DB::table('patient_billing')->insert(['visit_id' => $visitId]);
        $this->postJson('/__billing/bill', $this->billPayload($visitId))->assertConflict();
    }

    public function test_legacy_billing_rejects_an_explicit_visit_for_another_patient(): void
    {
        $visitId = $this->existingVisit(['patient_id' => 20]);
        $request = \Illuminate\Http\Request::create('/patient/gen-opd_bill', 'POST', $this->billPayload($visitId));
        $response = $this->legacy()->gen_opd_bill($request);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertDatabaseCount('patient_billing', 0);
    }

    public function test_referrals_follow_the_hospital_setting_and_ownership(): void
    {
        $visitId = $this->existingVisit();
        DB::table('ref_users')->insert([['id' => 3, 'hospital_id' => 1], ['id' => 4, 'hospital_id' => 2]]);
        $this->postJson('/__billing/visit', $this->visitPayload(['ref_by' => 3]))->assertOk();
        $this->assertDatabaseHas('visitlog', ['id' => $visitId, 'ref_by' => null]);
        $this->referralsEnabled = true;
        $this->postJson('/__billing/visit', $this->visitPayload(['ref_by' => 4]))->assertUnprocessable()->assertJsonValidationErrors('ref_by');
        $this->postJson('/__billing/visit', $this->visitPayload(['ref_by' => 3]))->assertOk();
        $this->assertDatabaseHas('visitlog', ['id' => $visitId, 'ref_by' => 3]);
    }

    public function test_billing_rejects_missing_or_invalid_item_quantities(): void
    {
        $visitId = $this->existingVisit();
        $this->postJson('/__billing/bill', $this->billPayload($visitId, ['charges' => []]))->assertUnprocessable()->assertJsonValidationErrors('charges');
        $this->postJson('/__billing/bill', $this->billPayload($visitId, ['qty' => [0]]))->assertUnprocessable()->assertJsonValidationErrors('qty.0');
        $this->assertDatabaseCount('patient_billing', 0);
    }
}
