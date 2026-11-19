<?php
require 'C:/xampp/htdocs/empath/vendor/autoload.php';
require 'C:/xampp/htdocs/empath/tests/Feature/BillingWorkflowTest.php';
$test = new Tests\Feature\BillingWorkflowTest('test_visit_is_persisted_before_billing_and_retry_reuses_it');
$app = $test->createApplication();
$runtime = __DIR__ . '/runtime';
@mkdir($runtime . '/views', 0777, true);
@mkdir($runtime . '/components/v2', 0777, true);
file_put_contents($runtime . '/components/v2/side-menu.blade.php', '<aside aria-label="Test fixture">Billing test</aside>');
config(['view.compiled' => $runtime . '/views']);
view()->getFinder()->prependLocation(__DIR__ . '/resources/views');
view()->getFinder()->prependLocation($runtime);
$user = Mockery::mock(App\Models\User::class)->makePartial();
$user->setRawAttributes(['id' => 9, 'org_id' => 1, 'name' => 'Test Staff', 'first_name' => 'Test', 'last_name' => 'Staff']);
$user->setRelation('roles', collect());
$user->shouldReceive('can')->andReturnUsing(fn ($ability, $args) => in_array($args[0], ['patient', 'opd_visits', 'opd_billing', 'opd_discount', 'add_referred_by']));
$user->shouldReceive('hasRole')->andReturn(false);
Illuminate\Support\Facades\Auth::setUser($user);
Illuminate\Support\Facades\Gate::define('module-permission', fn ($actor, ...$args) => $actor->can('module-permission', $args));
$cache = Mockery::mock(App\Cache\CacheManager::class);
$cache->shouldReceive('remember')->andReturn(collect());
$app->instance(App\Cache\CacheManager::class, $cache);
Illuminate\Support\Facades\Schema::create((new App\Models\Healtcare_org)->getTable(), function ($table) { $table->id(); $table->string('name'); });
Illuminate\Support\Facades\DB::table((new App\Models\Healtcare_org)->getTable())->insert(['id' => 1, 'name' => 'Test Clinic']);
Illuminate\Support\Facades\Schema::create('users', function ($table) {
    $table->id(); $table->integer('org_id'); $table->boolean('active');
    $table->string('name'); $table->string('first_name'); $table->string('last_name');
});
Illuminate\Support\Facades\DB::table('users')->insert(['id' => 9, 'org_id' => 1, 'active' => 1, 'name' => 'Test Staff', 'first_name' => 'Test', 'last_name' => 'Staff']);
Illuminate\Support\Facades\Route::post('/v2/billing/register-patient', fn () => null)->name('v2.billing.register_patient');
Illuminate\Support\Facades\Route::post('/v2/billing/prepare-visit', fn () => null)->name('v2.billing.prepare_visit');
Illuminate\Support\Facades\Route::post('/v2/billing/save', fn () => null)->name('v2.billing.save');
Illuminate\Support\Facades\Route::getRoutes()->refreshNameLookups();
Illuminate\Support\Facades\URL::forceRootUrl('http://127.0.0.1:8098');
$initialData = [
    'doctors' => [['id' => '2', 'name' => 'Test Doctor', 'spec' => 'General', 'specialization' => 'General']],
    'specs' => ['General'], 'categories' => [1 => 'Cash'], 'cash_category_id' => 1,
    'titles' => ['Mr.', 'Mrs.', 'Ms.', 'Baby'], 'genders' => ['Male', 'Female', 'Transgender', 'Other'],
    'services' => [['id' => 7, 'service_id' => 7, 'name' => 'Liver Function Test Basic', 'service_name' => 'Liver Function Test Basic', 'charges' => 200, 'price' => [1 => 200]], ['id' => 8, 'name' => 'ESR', 'price' => [1 => 100]], ['id' => 9, 'name' => 'Random Blood Sugar (RBS)', 'price' => [1 => 150]]],
    'consult_service_id' => 5, 'reg_service_id' => 6, 'ref_users' => [], 'is_ref_by_enabled' => false,
    'refund_approval_system' => false, 'is_admin' => false, 'user_name' => 'Test Staff',
];
$userPermissions = ['is_admin' => false, 'patient' => ['can_create' => true], 'opd_visits' => ['can_create' => true, 'can_read' => true], 'opd_billing' => ['can_create' => true, 'can_read' => true, 'can_update' => true], 'opd_discount' => ['can_update' => true]];
file_put_contents($runtime . '/fixture.html', view('v2.billing.index', compact('initialData', 'userPermissions'))->render());
echo "Rendered production billing template with isolated test data.\n";
