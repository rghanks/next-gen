<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\admin\PatientController;
use App\Http\Controllers\abdm\AbdmController;
use App\Http\Controllers\IPDPatientController;
use App\Http\Controllers\OPDVitalController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MessageCenter;
use App\Http\Controllers\SystemHealthController;
use Flasher\Laravel\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Auth::routes();

Route::get('/crypto/public-key', function () {
    abort_unless(
      
        config('request-encryption.enabled'),
        404
    );

    $path = config('request-encryption.public_key_path');

    abort_unless(file_exists($path), 404);

    return response()->file($path, [
        'Content-Type' => 'application/x-pem-file',
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma' => 'no-cache',
    ]);
})->name('crypto.public_key');

// WASA Phase 2: Rate-Limited Authentication & Credential Action Routes (5 attempts per 5 mins)
Route::middleware(['throttle:login'])->group(function () {
    Route::post('/login', [App\Http\Controllers\Auth\LoginController::class, 'login'])->middleware('decrypt.login');
    Route::post('/send-otp-to-mobile', [App\Http\Controllers\HomeController::class, 'sent_otp_to_phone'])->name('sent_otp_to_phone');
    Route::post('/verify_mobile_otp', [App\Http\Controllers\HomeController::class, 'verify_mobile_otp'])->name('verify_mobile_otp');
    Route::post('/verify_and_change_credential', [App\Http\Controllers\HomeController::class, 'verify_and_change_credential'])->name('verify_and_change_credential');
});
// Self-Hosted Short URL Redirect Route
Route::get('/s/{code}', [App\Http\Controllers\ShortUrlController::class, 'redirect'])
    ->where('code', '[a-zA-Z0-9_-]+')
    ->name('short_url.redirect');

// Super Admin direct login view route
Route::get('/admin-login', [App\Http\Controllers\Auth\LoginController::class, 'showSuperAdminLoginForm'])->name('superadmin.login');

// Cloud Login V2 Flow
Route::get('/login-v2', [App\Http\Controllers\Auth\LoginController::class, 'showLoginFormV2'])->name('login.v2');
Route::post('/login-v2', [App\Http\Controllers\Auth\LoginController::class, 'login'])->name('login.v2.submit');

Route::get('/', [App\Http\Controllers\HomeController::class, 'index']);
Route::get('/logout', [App\Http\Controllers\HomeController::class, 'logout']);
Route::get('/sign-up', [App\Http\Controllers\HomeController::class, 'sign_up'])->name('sign_up');
Route::post('/register', [App\Http\Controllers\HomeController::class, 'register_hospital'])->name('register_hospital');
Route::get('/verify-mobile-otp', [App\Http\Controllers\HomeController::class, 'verify_otp'])->name('verify_hospital_otp');
Route::get('/reset-password', [App\Http\Controllers\HomeController::class, 'enter_phone'])->name('enter_phone');
Route::get('/enter-otp', [App\Http\Controllers\HomeController::class, 'enter_otp'])->name('enter_otp');
Route::get('/change-credential', [App\Http\Controllers\HomeController::class, 'change_credential'])->name('change_credential');
Route::post('/verify_and_change_credential', [App\Http\Controllers\HomeController::class, 'verify_and_change_credential'])->name('verify_and_change_credential');

// Cloud Multi-Tenant Authentication Routes (IS_CLOUD=true)
Route::prefix('auth/cloud')->name('auth.cloud.')->group(function () {
    Route::post('/check-identifier', [App\Http\Controllers\Auth\CloudAuthController::class, 'checkIdentifier'])->name('check_identifier');
    Route::post('/send-otp', [App\Http\Controllers\Auth\CloudAuthController::class, 'sendOtp'])->name('send_otp');
    Route::get('/verify-email-link', [App\Http\Controllers\Auth\CloudAuthController::class, 'verifyEmailLink'])->name('verify_email_link');
    Route::post('/login-otp', [App\Http\Controllers\Auth\CloudAuthController::class, 'loginOtp'])->name('login_otp');
    Route::post('/password/identify', [App\Http\Controllers\Auth\CloudAuthController::class, 'passwordIdentify'])->name('password_identify');
    Route::post('/password/verify-otp', [App\Http\Controllers\Auth\CloudAuthController::class, 'passwordVerifyOtp'])->name('password_verify_otp');
    Route::post('/password/reset', [App\Http\Controllers\Auth\CloudAuthController::class, 'passwordReset'])->name('password_reset');
    Route::post('/request-access', [App\Http\Controllers\Auth\CloudAuthController::class, 'requestAccess'])->name('request_access');
    Route::post('/register/send-otp', [App\Http\Controllers\Auth\CloudAuthController::class, 'registerSendOtp'])->name('register_send_otp');
    Route::post('/register/verify-otp', [App\Http\Controllers\Auth\CloudAuthController::class, 'registerVerifyOtp'])->name('register_verify_otp');
    Route::get('/google/redirect', [App\Http\Controllers\Auth\CloudAuthController::class, 'googleRedirect'])->name('google_redirect');
    Route::get('/google/callback', [App\Http\Controllers\Auth\CloudAuthController::class, 'googleCallback'])->name('google_callback');
    Route::get('/set-password', [App\Http\Controllers\Auth\CloudAuthController::class, 'showSetPassword'])->name('set_password');
    Route::post('/set-password', [App\Http\Controllers\Auth\CloudAuthController::class, 'handleSetPassword'])->name('set_password.submit');
});

Route::get('/set-password', [App\Http\Controllers\Auth\CloudAuthController::class, 'showSetPassword'])->name('set_password.direct');
Route::post('/set-password', [App\Http\Controllers\Auth\CloudAuthController::class, 'handleSetPassword'])->name('set_password.direct.submit');



Route::get('/healthz', [SystemHealthController::class,'index'])->name('system.health');

Route::get('/reports/payment/generate-background',[App\Http\Controllers\ReportController::class, 'payment_report_generate_background'])->name('reports.payment.generate.background');
Route::get(
    '/inventory/indent-vs-billing-report',
    [App\Http\Controllers\InventoryController::class, 'indentVsBillingSingleIpd']
)->name('inventory.indent-vs-billing-report');

Route::get('excel-emport',[App\Http\Controllers\HomeController::class, 'excel_impot'])->name('excel.import');
Route::get('/navbar/notifications', [App\Http\Controllers\HomeController::class, 'getNavbarNotifications'])->name('navbar.notifications')->middleware('auth');
Route::get('/notifications/offcanvas/expiry-items', [App\Http\Controllers\HomeController::class, 'getExpiryItemsOffcanvas'])->name('notifications.offcanvas.expiry')->middleware('auth');
Route::get('/notifications/offcanvas/ipd-billing-requests', [App\Http\Controllers\HomeController::class, 'getIpdBillingRequestsOffcanvas'])->name('notifications.offcanvas.ipd_billing')->middleware('auth');

//video consent recording

Route::middleware(['jwt.verify'])->group(function () {

  // Route::get('/scan-documents', [App\Http\Controllers\HomeController::class, 'scan_documents'])->name('scan.documents');
  // Route::get('/js-qr-code', [App\Http\Controllers\HomeController::class, 'js_qr_code'])->name('js.qr.code');

  // Route::get('/scan-image/{name}', function ($name) {

  //     $basePath = env('SCAN_SOURCE');

  //     $name = basename($name); // strips traversal safely

  //     if (Str::contains($name, ['../', '..\\'])) {
  //         abort(403);
  //     }

  //     $fullPath = $basePath . '/' . $name;

  //     if (!File::exists($fullPath)) {
  //         abort(404);
  //     }

  //     return response()->file($fullPath);

  // })->name('scan.image');

  // Route::post('/scan-complete', [App\Http\Controllers\HomeController::class, 'upload_file_in_database'])->name('scan.complete');



  Route::get('/live/video/consent', [\App\Http\Controllers\HomeController::class,'video_consent'])->name('api.video_consent');
  Route::post('patient/video/consent',[\App\Http\Controllers\HomeController::class,'store_video_consent'])->name('recordings.store');
  Route::get('download/video/consent/{org_id}/{visit_id}',[\App\Http\Controllers\HomeController::class,'download_video_consent'])->name('recordings.consent.download');
  Route::post('/recordings/chunk', [\App\Http\Controllers\HomeController::class, 'storeChunk'])->name('recordings.storeChunk');
  Route::get('/recordings/list/{consent_id}', [\App\Http\Controllers\HomeController::class, 'listChunks'])->name('recordings.list');
  Route::post('/recordings/merge', [\App\Http\Controllers\HomeController::class, 'mergeChunks'])->name('recordings.merge');
  Route::post('/recordings/store-final-video', [\App\Http\Controllers\HomeController::class, 'storeFinalVideo'])->name('recordings.storeVideo');


  // Route::get('patient-file-scanner',[\App\Http\Controllers\HomeController::class, 'file_scanner'])->name('file_scanner');
  // Route::post('patient/barcode-auto-upload',[\App\Http\Controllers\HomeController::class, 'upload_by_barcode'])->name('patient.uploadByBarcode');

  // Route::get('/documents/sync', [\App\Http\Controllers\HomeController::class,'syncDocuments'])->name('sync.documents');
  // Route::get('/download/documents/{id}',[\App\Http\Controllers\HomeController::class,'download_documents'])->name('document.download');
  // Route::get('/documents/mark-downloaded', [\App\Http\Controllers\HomeController::class,'markDocumentsDownloaded'])->name('mark.documents.downloaded');

    // Route::post('patient/barcode-auto-upload',[\App\Http\Controllers\HomeController::class, 'upload_by_qr'])->name('patient.uploadByBarcode');

});


  Route::post('patient/barcode-auto-upload',[\App\Http\Controllers\HomeController::class, 'upload_by_qr'])->name('patient.uploadByBarcode');

  Route::get('/documents/pull-from-live', [ \App\Http\Controllers\HomeController::class,'pullDocumentsFromLive'])->name('documents.pull_from_live');


Route::get('/documents-download',[App\Http\Controllers\HomeController::class,'pullAndSaveDocumentsInCustomFolder'])->name('documents.download');
Route::get('/consent-download',[App\Http\Controllers\HomeController::class,'pullAndSaveInCustomFolder'])->name('consent.download');
Route::get('/recordings/pull-from-live', [ \App\Http\Controllers\HomeController::class,'pullRecordingsFromLive'])->name('recordings.pull_from_live');


// SUPER ADMIN ROUTES
Route::middleware(['auth','superadmin'])->group(function () {

  Route::get('/superadmin-dashboard', [App\Http\Controllers\AdminController::class, 'admin_dashboard'])->name('superadmin.dashboard');
  Route::match(['get', 'post'], '/admin-logout', [App\Http\Controllers\AdminController::class, 'admin_logout'])->name('admin.logout');
  Route::get('/hospital_list', [App\Http\Controllers\AdminController::class, 'hospital_list'])->name('hospital.list');
  Route::get('/get_hospital_list_data', [App\Http\Controllers\AdminController::class, 'get_hospital_list_data'])->name('hospital.list_data');
  Route::get('/hospital_dashboard', [App\Http\Controllers\AdminController::class, 'hospital_dashboard'])->name('hospital.dashboard');
  Route::get('/get_hospital_dashboard_data', [App\Http\Controllers\AdminController::class, 'get_hospital_dashboard_data'])->name('hospital.dashboard_data');
  Route::get('/add_hospital', [App\Http\Controllers\AdminController::class, 'add_hospital'])->name('add.hospital');
  Route::post('/save_hospital', [App\Http\Controllers\AdminController::class, 'save_hospital'])->name('save.hospital');
  Route::get('/edit_hospital/{id}', [App\Http\Controllers\AdminController::class, 'edit_hospital'])->name('edit.hospital');
  Route::post('/update_hospital/{id}', [App\Http\Controllers\AdminController::class, 'update_hospital'])->name('update.hospital');
  Route::get('/delete_hospital/{id}', [App\Http\Controllers\AdminController::class, 'delete_hospital'])->name('delete.hospital');
  Route::get('/changeHospStatus',[App\Http\Controllers\AdminController::class,'hospital_status_update'])->name('hospital.status_update');
  Route::get('/hospital_setting_status_update',[App\Http\Controllers\AdminController::class,'hospital_setting_status_update'])->name('hospital_setting_status_update');

  Route::get('/verify_hospital/{id}',[App\Http\Controllers\AdminController::class,'verify_hospital'])->name('hospital.verify');
  Route::post('/hospital/resend-set-password', [App\Http\Controllers\AdminController::class, 'resend_set_password_email'])->name('hospital.resend_set_password');
  Route::post('/hospital/update-client-id', [App\Http\Controllers\AdminController::class, 'update_client_id'])->name('hospital.update_client_id');
  Route::post('/hospital/generate-client-secret', [App\Http\Controllers\AdminController::class, 'generate_client_secret'])->name('hospital.generate_client_secret');
  Route::post('/hospital/update-empath-token', [App\Http\Controllers\AdminController::class, 'update_hospital_empath_token'])->name('hospital.update_empath_token');
  Route::post('/hospital/fetch-license', [App\Http\Controllers\AdminController::class, 'fetch_hospital_license'])->name('hospital.fetch_license');


  //  Route::get('/list_hospital_consumable', [App\Http\Controllers\AdminController::class, 'list_hospital_consumable'])->name('list.hospital_consumable');
  // Route::post('/save_hospital_consumable', [App\Http\Controllers\AdminController::class, 'save_hospital_consumable'])->name('save.hospital_consumable');
  //  Route::post('/update_hospital_consumable', [App\Http\Controllers\AdminController::class, 'update_hospital_consumable'])->name('update.hospital_consumable');
  //  Route::get('/consumablechangeStatus',[App\Http\Controllers\AdminController::class,'consumable_status_update'])->name('consumable.status_update');

  Route::get('/list_hospital_pharmacy', [App\Http\Controllers\AdminController::class, 'list_hospital_pharmacy'])->name('list.hospital_pharmacy');
  Route::post('/save_hospital_pharmacy', [App\Http\Controllers\AdminController::class, 'save_hospital_pharmacy'])->name('save.hospital_pharmacy');
  Route::get('/pharmacychangeStatus',[App\Http\Controllers\AdminController::class,'pharmacy_status_update'])->name('pharmacy.status_update');
  Route::post('/update_hospital_pharmacy', [App\Http\Controllers\AdminController::class, 'update_hospital_pharmacy'])->name('update.hospital_pharmacy');



  Route::get('clear_database',[App\Http\Controllers\AdminController::class,'clear_database'])->name('clear.database');
  Route::get('delete_data',[App\Http\Controllers\AdminController::class,'delete_data'])->name('delete_data');

  Route::get('get_query',[App\Http\Controllers\AdminController::class,'get_query'])->name('get_query');
  Route::post('/save-queries', [App\Http\Controllers\AdminController::class, 'saveQueries'])->name('save.queries');
  Route::post('/execute-queries', [App\Http\Controllers\AdminController::class, 'executeQuery'])->name('execute.queries');

  Route::get('/create-template',[App\Http\Controllers\AdminController::class,'create_template'])->name('create.template');
  Route::post('/save-template',[App\Http\Controllers\AdminController::class,'save_template'])->name('save.template');
  Route::get('/add-template',[App\Http\Controllers\AdminController::class,'add_template'])->name('add.template');
  Route::post('/attach-template',[App\Http\Controllers\AdminController::class,'attach_template'])->name('attach.template');


  // Settings Routes
  Route::get('/superadmin-settings', [App\Http\Controllers\AdminController::class, 'settings'])->name('superadmin_settings');
  // Route::get('/sms_setting_status_update', [App\Http\Controllers\AdminController::class, 'sms_setting_status_update'])->name('sms_setting_status_update');
  // Route::get('/whatsapp_status_update', [App\Http\Controllers\AdminController::class, 'whatsapp_status_update'])->name('whatsapp_status_update');

  // Hospital Routes
  Route::get('/hospital-detailed-report', [App\Http\Controllers\HospitalReportController::class, 'detailed_report'])->name('hospital_reports.detailed');

  // DRUG DB ROUTES
  Route::group(['prefix' => 'drug-db'], function () {
    //Drug Salts
    Route::get('/drug-salts',[App\Http\Controllers\DrugsDBController::class,'drug_salts'])->name('drug_salts.list');
    Route::post('/save-drug-salt',[App\Http\Controllers\DrugsDBController::class,'save_drug_salt'])->name('save.drug_salt');

    Route::get('/add-brand-and-dosage-to-drug-salt/{id}',[App\Http\Controllers\DrugsDBController::class,'add_drug_salt_brand_and_dosage'])->name('add_drug_salt_brand_and_dosage');
    Route::post('/save-drug-salt-dosage',[App\Http\Controllers\DrugsDBController::class,'save_drug_salt_dosage'])->name('save.drug_salt_dosage');
    Route::post('/save-brand-to-drug-salt',[App\Http\Controllers\DrugsDBController::class,'save_brand_to_drug_salt'])->name('save.save_brand_to_drug_salt');

    //Drug Combinations
    Route::get('/drug-combinations',[App\Http\Controllers\DrugsDBController::class,'drug_combinations'])->name('drug_combinations.list');
    Route::post('/save-drug-combinations',[App\Http\Controllers\DrugsDBController::class,'save_drug_combinations'])->name('save.drug_combinations');

    Route::get('/add-brand-to-drug-combinations/{id}',[App\Http\Controllers\DrugsDBController::class,'add_drug_combination_brand'])->name('add_brand_to_drug_combinations');
    Route::post('/save-drug-combinations-dosage',[App\Http\Controllers\DrugsDBController::class,'save_drug_combinations_dosage'])->name('save.drug_combinations_dosage');
    Route::post('/save-brand-to-drug-combinations',[App\Http\Controllers\DrugsDBController::class,'save_brand_to_drug_combinations'])->name('save.save_brand_to_drug_combinations');

    Route::get('/search-drugs',[App\Http\Controllers\DrugsDBController::class,'search_drugs'])->name('search.drugs');

    // Company
    Route::get('/companies',[App\Http\Controllers\DrugsDBController::class,'companies'])->name('companies.list');
    Route::post('/save-company',[App\Http\Controllers\DrugsDBController::class,'save_company'])->name('save.company');
    Route::post('/store', [App\Http\Controllers\DrugsDBController::class, 'storeCompany']);
    Route::post('/update', [App\Http\Controllers\DrugsDBController::class, 'updateCompany']);
    Route::get('/edit/{id}', [App\Http\Controllers\DrugsDBController::class, 'editCompany']);

    // Drug Dosages
    Route::get('/drug-dosages',[App\Http\Controllers\DrugsDBController::class,'drug_dosages'])->name('drug_dosages.list');
    Route::post('/save-drug-dosage',[App\Http\Controllers\DrugsDBController::class,'save_drug_dosage'])->name('save.drug_dosage');
    Route::post('/storeDose', [App\Http\Controllers\DrugsDBController::class, 'storeDose']);
    Route::post('/updateDose', [App\Http\Controllers\DrugsDBController::class, 'updateDose']);
    Route::get('/editDose/{id}', [App\Http\Controllers\DrugsDBController::class, 'editDose']);

    // Dose Unit
    Route::get('/dose-unit',[App\Http\Controllers\DrugsDBController::class,'dosage_unit'])->name('dosage_unit.list');
    Route::post('/storeDoseUnit', [App\Http\Controllers\DrugsDBController::class, 'storeDoseUnit']);
    Route::post('/updateDoseUnit', [App\Http\Controllers\DrugsDBController::class, 'updateDoseUnit']);
    Route::get('/editDoseUnit/{id}', [App\Http\Controllers\DrugsDBController::class, 'editDoseUnit']);

    // Dosage Form
    Route::get('/dosage-forms',[App\Http\Controllers\DrugsDBController::class,'dosage_forms'])->name('dosage_forms.list');
    Route::post('/save-dosage-form',[App\Http\Controllers\DrugsDBController::class,'save_dosage_form'])->name('save.dosage_form');
    Route::post('/storeDoseForm',[App\Http\Controllers\DrugsDBController::class,'storeDoseForm']);
    Route::get('/editDoseForm/{id}', [App\Http\Controllers\DrugsDBController::class, 'editDoseForm']);
    Route::post('/updateDoseForm',[App\Http\Controllers\DrugsDBController::class,'updateDoseForm']);

    // Packing
    Route::get('/drug-packing',[App\Http\Controllers\DrugsDBController::class,'drug_packing'])->name('drug_packing.list');
    Route::post('/storeDrugPacking',[App\Http\Controllers\DrugsDBController::class,'storeDrugPacking']);
    Route::get('/editDrugPacking/{id}', [App\Http\Controllers\DrugsDBController::class, 'editDrugPacking']);
    Route::post('/updateDrugPacking',[App\Http\Controllers\DrugsDBController::class,'updateDrugPacking']);

    // Single Unit Packing

    Route::get('/drug-single-packing',[App\Http\Controllers\DrugsDBController::class,'singleUnitPacking'])->name('single_unit_packing.list');
    Route::post('/storeSingleUnitPacking',[App\Http\Controllers\DrugsDBController::class,'storeSingleUnitPacking']);
    Route::get('/editSingleUnitPacking/{id}', [App\Http\Controllers\DrugsDBController::class, 'editSingleUnitPacking']);
    Route::post('/updateSingleUnitPacking',[App\Http\Controllers\DrugsDBController::class,'updateSingleUnitPacking']);

    // Add Drug Combination name
    Route::post('/save-combination-name',[App\Http\Controllers\DrugsDBController::class, 'saveCombinationName'])->name('save.combination.name');

    Route::post('/active-salt',[App\Http\Controllers\DrugsDBController::class,'toggleIsVaccine'])->name('active-salt');
    Route::post('/active-combinations',[App\Http\Controllers\DrugsDBController::class,'combinationsIsVaccine'])->name('active-combinations');
    // Brand Master
    // Brand Master
    Route::get('/brand-master',[App\Http\Controllers\DrugsDBController::class,'brand_master'])->name('brand_master.list');
    Route::get('/edit-brand-data/{id}',[App\Http\Controllers\DrugsDBController::class,'edit_brand_master'])->name('brand_master.edit');

    Route::get('/brand-form-list',[App\Http\Controllers\DrugsDBController::class,'brand_form_list'])->name('brand_form.list');
    Route::post('/brand-form-update',[App\Http\Controllers\DrugsDBController::class,'brand_form_update'])->name('brand_form.update');
    Route::get('/brand-bulk-sample', [App\Http\Controllers\DrugsDBController::class, 'brand_bulk_sample'])->name('brand_form.bulk_sample');
    Route::post('/brand-bulk-import', [App\Http\Controllers\DrugsDBController::class, 'brand_bulk_import'])->name('brand_form.bulk_import');
    Route::get('/brand-bulk-review/{table}', [App\Http\Controllers\DrugsDBController::class, 'brand_bulk_review'])->name('brand_form.bulk_review');
    Route::post('/brand-bulk-update-row', [App\Http\Controllers\DrugsDBController::class, 'brand_bulk_update_row'])->name('brand_form.bulk_update_row');
    Route::post('/brand-bulk-finish', [App\Http\Controllers\DrugsDBController::class, 'brand_bulk_finish'])->name('brand_form.bulk_finish');

    // Pending Medicines Approval
    Route::get('/pending-medicines', [App\Http\Controllers\DrugsDBController::class, 'pending_medicines'])->name('drug_db.pending_medicines');
    Route::post('/pending-medicines/approve/{id}', [App\Http\Controllers\DrugsDBController::class, 'approve_pending_medicine'])->name('drug_db.pending_medicines.approve');
    Route::post('/pending-medicines/reject/{id}', [App\Http\Controllers\DrugsDBController::class, 'reject_pending_medicine'])->name('drug_db.pending_medicines.reject');
    Route::post('/pending-medicines/update/{id}', [App\Http\Controllers\DrugsDBController::class, 'update_pending_medicine'])->name('drug_db.pending_medicines.update');
    Route::delete('/pending-medicines/delete/{id}', [App\Http\Controllers\DrugsDBController::class, 'delete_pending_medicine'])->name('drug_db.pending_medicines.delete');
  });
});
/* ############ App License Check  ############## */
Route::middleware('app-license')->group(function () {


  // Hospital User Routes
  Route::middleware(['auth', 'user-access:staff'])->group(function () {

    Route::post('authenticate-user-password', [App\Http\Controllers\admin\AuthController::class, 'authenticate_user_password'])->name('user.authenticate_password');

    /*
     * ABDM / ABHA Cloud & Local Agent Management Console Routes (Protected by RBAC and ABHA Module License)
     */
    Route::controller(AbdmController::class)->prefix('admin/abdm')->middleware(['module-permission:settings,can_update', 'module-license:abha'])->group(function () {
        Route::get('cloud', 'cloudDashboard')->name('abdm.cloud_dashboard');
        Route::get('incentives', 'viewIncentivesPage')->name('admin.abdm.incentives');
        Route::get('cloud/health', 'health')->name('abdm.cloud_health');
        Route::post('cloud/generate-keys', 'generateEncryptionKeys')->name('abdm.cloud_keys');
        Route::post('cloud/encrypt', 'encryptData')->name('abdm.cloud_encrypt');
        Route::post('cloud/decrypt', 'decryptData')->name('abdm.cloud_decrypt');
        Route::post('cloud/raw-relay', 'sendRawCloudRelay')->name('abdm.cloud_raw_relay');
        Route::get('cloud/audit-record/{id}', 'getAuditLogRecord')->name('abdm.cloud_audit_record');
        Route::post('cloud/simulate-scan-share', 'simulateScanShare')->name('abdm.cloud_simulate_scan_share');
        Route::get('cloud/fhir-preview/{referenceNumber}', 'previewFhirJson')->name('abdm.cloud_fhir_preview');
        Route::get('cloud/perf/patients', 'getPerfPatients')->name('abdm.cloud_perf_patients');
        Route::get('cloud/perf/patient-contexts/{patientId}', 'getPerfPatientContexts')->name('abdm.cloud_perf_contexts');
        Route::post('cloud/perf/generate-bundles', 'generatePerfBundles')->name('abdm.cloud_perf_generate');
        Route::get('cloud/validator/status', 'getValidatorStatus')->name('abdm.cloud_validator_status');
        Route::post('cloud/validator/run', 'runBundleValidator')->name('abdm.cloud_validator_run');
        Route::post('cloud/validator/start-live', 'startLiveValidator')->name('abdm.cloud_validator_start_live');
        Route::get('cloud/validator/poll-live', 'pollLiveValidator')->name('abdm.cloud_validator_poll_live');
        Route::get('cloud/validator/stream', 'streamBundleValidator')->name('abdm.cloud_validator_stream');
        Route::get('cloud/validator/download-report/{filename}', 'downloadValidatorReport')->name('abdm.cloud_validator_download_report');
        Route::get('cloud/validator/results', 'getValidatorResultsList')->name('abdm.cloud_validator_results');
        Route::get('cloud/validator/results/{filename}', 'getValidatorResultDetail')->name('abdm.cloud_validator_result_detail');
        Route::post('cloud/validator/revalidate', 'revalidateBundle')->name('abdm.cloud_validator_revalidate');
        Route::post('cloud/validator/delete', 'deleteValidatorResult')->name('abdm.cloud_validator_delete');
        Route::post('cloud/validator/delete-all', 'deleteAllValidatorResults')->name('abdm.cloud_validator_delete_all');
        Route::post('cloud/validator/process-pending', 'processPendingValidation')->name('abdm.cloud_validator_process_pending');
        Route::get('cloud/validator/download-result/{filename}', 'downloadValidatorResultFile')->name('abdm.cloud_validator_download_result');
    });

    Route::controller(PatientController::class)->group(function(){

        Route::get('receive-payment','receive_payment')->name('receive.payment');

        Route::get('/culture/get-drugs-data', 'getDrugsData')->name('get_drugs_data');
        Route::get('/culture/get-drugs', 'getDrugs')->name('get_drugs');

        Route::get('patient/list','list')->name('patient.list');
        Route::get('patient/register','register')->name('patient.register')->defaults('context', 'register');
        Route::get('v2/patient/register','register_v2')->name('patient.register.v2') ->defaults('context', 'register');
        Route::get('v3/patient/register','register_v3')->name('patient.register.v3')->defaults('context', 'register');
        Route::get('registration','registration')->name('patient.registration');

        // ER MODULE
        Route::middleware(['module-license:er'])->group(function () {
          Route::get('patient/er-register','er_register')->name('er.patient.register')->defaults('context', 'register');
          Route::get('clinical/er-note/{vid?}/{t_id?}', 'er_note')->name('clinical.er_note');
          Route::get('patient/er-view', 'er_patient_view')->name('er.patient.view');
          Route::get('patient/er-detail/{vid}', 'er_patient_detail')->name('er.patient.detail');
        });

        Route::get('/get-service-items', 'get_services_items')->name('patient.get_services_items');

        Route::get('fetch-er-charges','fetch_er_charges')->name('fetch.er_charges');

        Route::post('/patient/add-visit-v3','add_visit_v3')->name('patient.add_visit.v3');

        Route::get('verify-mobile-no','verify_mobile_no')->name('verify.mobile.no');
        Route::post('/update-mobile-no','update_mobile_no')->name('update.mobile.no');

        Route::get('/check-mobile', 'checkMobile')->name('check.mobile');
        Route::get('/get-names', 'getName')->name('get.names');

        Route::middleware(['module-license:abha'])->group(function () {
            Route::get('patient/fetch-by-abha', 'fetch_by_abha')->name('patient.fetch_by_abha');
            Route::post('patient/unlink-abha', 'unlink_abha')->name('patient.unlink_abha');
            Route::post('patient/update-abha', 'update_abha')->name('patient.update_abha');
            Route::post('patient/sync-abha-demographics', 'syncAbhaDemographics')->name('patient.sync_abha_demographics');
            Route::match(['get', 'post'], 'patient/check-abha-alignment', 'checkAbhaDemographicAlignment')->name('patient.check_abha_alignment');
            Route::post('patient/generate-link-token', 'generateAbhaLinkToken')->name('patient.generate_link_token');
            Route::get('patient/abha-demographic-history', 'getAbhaDemographicHistory')->name('patient.abha_demographic_history');
            Route::get('patient/get-abha-profile-demographics', 'getAbhaProfileDemographics')->name('patient.get_abha_profile_demographics');
            Route::post('patient/check-local-by-mobile', 'check_local_by_mobile')->name('patient.check_local_by_mobile');
            Route::post('patient/update-visit-abdm-status', 'update_visit_abdm_status')->name('patient.update_visit_abdm_status');
        });

        Route::post('verify-otp','verify_otp')->name('verify_otp');
        Route::post('resend-otp','resend_otp')->name('resend_otp');

        Route::get('version_control/{flag}', 'version_control')->name('version_control');

        Route::get('patient/visit-history','visit_history')->name('patient.visit_history');
        Route::get('patient/bills-overview','patient_bills_overview')->name('patient.bills_overview');

        Route::post('lock-bill/{id}','lock_bill')->name('lock.bill');

        Route::get('get-doc-type','get_document_type')->name('get.doc.type');


        Route::post('/patient/auto-upload', 'autoUpload')->name('patient.autoUpload');

        Route::post('/patient/qr-auto-upload', 'upload_by_qr')->name('patient.uploadByQR');
        Route::post('camera/upload-consent', 'camera_upload_consent')->name('camera.upload.consent');




        // Route::get('testBill',function(){
        //     return view('admin.patient.v2.newBilling');
        // });

        Route::get('create-doctor-team','create_doctor_team')->name('patients.create_doctor_team');
        Route::post('save-doctor-team','save_doctor_team')->name('patients.save_doctor_team');
        Route::get('doctor-team-includes/{id}','doctor_team_includes')->name('patients.doctor_team_includes');
        Route::post('update-include-doctor-team/{id}','update_includes_doctor_team')->name('update_includes.doctor_team');


        Route::get('patient-profile/{id?}','profile')->name('patient.profile');
        Route::get('bill-cancel-request','bill_cancel_request')->name('bill_cancel_request');
        Route::get('/previous-visit-details', 'previous_visit_details')->name('previous_visit_details');

        Route::get('patient/new-billing','newBilling')->name('patient.newBilling');

        Route::post('patient/save','save')->name('patient.save');
        Route::post('patient/save2','save2')->name('patient.save2');
        Route::get('patient/view/{id}','patient_view')->name('patient.view');
        Route::get('patient/edit/{id}','edit')->name('patient.edit');
        Route::get('get-patient-profile-for-edit','get_profile_for_edit')->name('patient.get_profile_for_edit');
        Route::get('get-patient-health-chart','get_health_chart')->name('patient.get_health_chart');
        Route::post('patient/update/{id}','update')->name('patient.update');
        Route::get('patient/edit/{id?}','patedit')->name('patient.patedit');
        Route::post('patient/edit','pat_edit')->name('patient.pat_edit');
        Route::post('patient/pat_save','pat_save')->name('patient.pat_save');
        Route::post('patient_filter','patient_filter')->name('patient.filter');
        Route::get('patient/visit/{id}','add_visit')->name('patient.visit');
        Route::post('patient/add_visit','patient_add_visit')->name('patient.add_visit');
        Route::get('patient/visit_data/{uhid}/{vid}','visit_data')->name('patient.visit_data');

        Route::get('patient/opd-audit-log/{id}','opd_audit_log')->name('patient.opd_audit_log');
        Route::get('patient/ipd-audit-log/{id}','ipd_audit_log')->name('patient.ipd_audit_log');
        Route::get('patient/bill-audit-log/{id}','bill_audit_log')->name('patient.bill_audit_log');
        Route::get('ipd-bill-audit-log/{id}','ipd_bill_audit_log')->name('patient.ipd_bill_audit_log');

        Route::get('patient/visit_form/{id}','visit_form')->name('patient.visit_form');
        Route::get('patient/visit_form5/{id}','visit_form5')->name('patient.visit_form5');
        Route::get('patient/visit_form2/{id}','visit_form2')->name('patient.visit_form2');
        Route::get('patient/get_ref_by_team','get_ref_by_team')->name('patient.get_ref_by_team');
        Route::post('patient/update_ref_by_team','update_ref_by_team')->name('patient.update_ref_by_team');

        Route::get('patient/viewbill/{id}/{vid}','patient_viewbill')->name('patient.viewbill');
        Route::get('patient/opd-billing','opd_billing')->name('patient.opd_billing');
        Route::post('patient/start-opd_billing','start_opd_billing')->name('patient.start_opd_billing');
        Route::get('patient/get-opd_billing-layout','get_opd_billing_layout')->name('patient.get_opd_billing_layout');
        Route::post('patient/gen-opd_bill','gen_opd_bill')->name('patient.gen_opd_bill');

        // Register V5
        Route::post('patient/start-opd_billing_v5','start_opd_billing_v5')->name('patient.start_opd_billing_v5');
        Route::post('patient/gen-opd_bill_v5','gen_opd_bill_v5')->name('patient.gen_opd_bill_v5');

        //Emergency Module
        Route::get('patient/emergency/{id?}','emergency_module')->name('patient.emergency_module');
        Route::post('er/consulation-request/{id}','consultation_request')->name('er.consultation_request');
        Route::post('update-con-status','update_consultation_status')->name('update.consultation.status');

        Route::get('suggestions','suggestions')->name('suggestions');
        Route::get('clinical_comments','clinical_comments')->name('clinical_comments');
        Route::get('patient/get_vital_list','get_vital_list')->name('patient.get_vital_list');
        Route::post('patient/start_patient','start_patient')->name('patient.start_patient');
        Route::get('open_draft_bill/{bill_id}','open_draft_bill')->name('patient.open_draft_bill');
        Route::post('patient/add_comments','add_comments')->name('patient.add_comments');
        Route::get('patient/fetch_comments_section','fetech_comment_section')->name('patient.fetech_comment_section');
        Route::post('patient/delete_comment','delete_comment')->name('patient.delete_comment');


        Route::post('clinical/takeover-page-lock', 'takeOverEdit')->name('takeOverEdit');
        Route::post('clinical/ping-page-lock', 'pingOverEdit')->name('pingOverEdit');
        Route::post('clinical/release-page-lock', 'releasePageLock')->name('releasePageLock');

        // clinical parts - add vitals
        // Route::get('patient/vitals/{vid}','vitals')->name('patient.vitals');
        Route::post('patient/save_vitals','save_vitals')->name('patient.save_vitals');
        Route::get('patient/eye_clinical/{vid?}','clinical_notes')->name('patient.eye_clinical');
        Route::post('patient/save_freetext','save_freetext')->name('patient.save_freetext');
        Route::post('patient/save_medicine','eye_save_medicine')->name('patient.medicine.store');
        Route::get('patient/eye_clinical_print/{id}','eye_clinical_print')->name('patient.eye_clinical_print');
        Route::get('patient/print_prescription/{id}','print_prescription')->name('patient.print_prescription');
        Route::get('patient/surgery_notes/{id}/{t_id?}','surgery_notes')->name('patient.surgery_notes');
        Route::post('patient/save_surgery_notes','save_surgery_notes')->name('patient.save_surgery');
        Route::get('patient/print_surgical_notes/{id}','print_surgical_notes')->name('patient.print_surgical_notes');
        Route::post('patient/save_as_draft_surgery_notes/{id}','save_as_draft_surgery_notes')->name('patient.save_as_draft_surgery_notes');

        /* Start Register Lead */
        Route::get('patient/register_lead','register_lead')->name('patient.register_lead');
        Route::post('patient/save_regi_lead','save_regi_lead')->name('patient.save_regi_lead');
        Route::post('/register-lead/update', 'update_regi_lead')->name('patient.update_regi_lead');
        Route::post('/register-lead/delete', 'delete_regi_lead')->name('patient.delete_regi_lead');

        Route::post('patient/create-direct-visit','create_direct_vist')->name('patient.create_direct_visit');
        /* End Register Lead */

        Route::get('patient/vitals/{vid?}', 'add_vitals')->name('patient.add_vitals');

        Route::middleware(['module-license:opd_clinical'])->group(function () {

          Route::get('clinical/dashboard', 'clinical_dashboard')->name('clinical.dashboard');
          Route::middleware(['module-license:opd_clinical.general_prescription'])->group(function () {
              Route::get('clinical/general/{vid?}/{t_id?}', 'general_prescription')->name('clinical.general');
              Route::get('clinical/general-v2/{vid?}/{t_id?}', 'general_prescription_v2')->name('clinical.general_v2');
              Route::get('clinical/general-v4/{vid?}/{t_id?}', 'general_prescription_v4')->name('clinical.general_v4');
              Route::get('clinical/general-v3/{vid?}/{t_id?}', 'general_prescription_v3')->name('clinical.general_v3');
              Route::get('clinical/previous-notes/{visit_id}', 'get_previous_notes_list')->name('clinical.previous_notes_list');
              Route::get('clinical/check-previous-doctor/{visit_id}', 'check_previous_doctor')->name('clinical.check_previous_doctor');
          });

          Route::middleware(['module-license:opd_clinical.urology'])->group(function () {
              Route::get('patient/urology/v2/{vid?}/{t_id?}', 'urology_v2')->name('patient.urology_v2');
              Route::get('patient/general/v2/{vid?}/{t_id?}','urology_v2')->name('patient.general_v2');
          });

          Route::middleware(['module-license:opd_clinical.cardiology'])->group(function () {
              Route::get('patient/cardio-notes/{vid?}/{t_id?}', 'cardio_notes')->name('patient.cardio_notes');
              Route::get('patient/dermatology-notes/{vid?}/{t_id?}', 'dermatology_notes')->name('patient.dermatology_notes');
          });

          Route::get('patient/dental-notes/{vid?}/{t_id?}', 'dental_notes')->name('patient.dental_notes');


          Route::middleware(['module-license:opd_clinical.ophthalmology'])->group(function () {
            Route::get('patient/eye_clinical-v2/{vid?}/{t_id?}','eye_clinical_v2')->name('patient.eye_clinical.v2');
          });

          Route::middleware(['module-license:opd_clinical.gyneacology'])->group(function () {
             Route::get('patient/gyneacology-clinical-notes/{vid?}/{t_id?}','gynea_clinical_notes')->name('patient.gynea_clinical_notes');
          });

          Route::middleware(['module-license:opd_clinical.orthopedic'])->group(function () {
            Route::get('patient/ortho-clinical-notes-v2/{vid?}/{t_id?}','ortho_clinical_notes_v2')->name('patient.ortho_clinical_notes.v2');
          });
          Route::middleware(['module-license:opd_clinical.nephrology'])->group(function () {
            Route::get('patient/nephrology-notes/{vid?}/{t_id?}','nephrology_clinical_notes')->name('patient.nephrology_clinical_notes');
          });

          Route::middleware(['module-license:opd_clinical.neurology'])->group(function () {
            Route::get('patient/neurosurgery/{vid?}/{t_id?}','neurosurgery_notes')->name('patient.neurosurgery_notes');
          });

          //Common clinical routes
          Route::get('clinical/get-diabetic-basic-details/{vid?}', 'get_diabetic_basic_detail_form')->name('clinical.diabetic_basic_detail');
          Route::get('clinical/get-diabetic-logs/{vid?}', 'get_diabetic_care_log_form')->name('clinical.diabetic_logs');

        });



        


        // Route::get('patient/video/consent/','video_consent')->name('patient.video_consent');
        // Route::post('patient/video/consent','store_video_consent')->name('recordings.store');

        Route::post('patient/general_prescription/save','save_general_prescription')->name('patient.general_prescription.store');
        Route::post('/general/save_as_template','save_template_general')->name('general.save_as_template');
        Route::get('/ipd-load-template','load_ipd_notes_template')->name('ipd.notes_template');
        Route::post('patient/approve_prescription','approve_prescription')->name('patient.approve_prescription');
        Route::post('patient/reopen_prescription','reopen_prescription')->name('patient.reopen_prescription');
        Route::get('patient/save-doctor-patient-tag','save_doctor_patient_tag')->name('save.doctor_patient_tag');
        Route::get('patient/remove-doctor-patient-tag','remove_doctor_patient_tag')->name('remove.doctor_patient_tag');


        Route::get('drug-db/drug-composition-form','drug_coposition_form')->name('drug_db.drug_coposition_form');
        Route::post('drug-db/save-drug-composition-single','save_drug_combo_single')->name('drug_db.save_drug_combo_single');
        Route::get('drug-db/drug-composition-search','drug_coposition_search')->name('drug_db.composition_search');



        Route::middleware(['module-license:ipd_clinical'])->group(function () {

          Route::get('clinical/ipd_notes/{vid?}','ipd_notes')->name('clinical.ipd_notes');
          Route::get('/ipd/print-notes/{id}','print_ipd_notes')->name('ipd.print_notes');
          Route::get('/nurse/print-notes/{id}','print_nursing_notes')->name('nurse.print_notes');
          Route::post('ipd/delete-notes','delete_ipd_notes')->name('ipd.delete_note');
          Route::get('/ipd-note-row', 'add_ipd_new_note')->name('ipd.add_new_note');
          Route::post('/notes/update-doctor','update_notes_doctor')->name('update.notes.doctor');

        });

        Route::post('vitals/pain/save','savePainLevel')->name('vitals.pain.save');
        Route::post('update/fud/{visit_id}','update_fud')->name('update.fud');
        Route::post('update/fud_v2/{visit_id}','update_fud_v2')->name('update.fud_v2');
        //########################################### nursing module #####################################

        Route::get('/nursing/admission-assessment/{id?}','admission_assessment')->name('nursing.admission_assessment');
        Route::post('patient/save_admission_assessment','save_admission_assessment')->name('patient.admission_assessment');
        Route::get('patient/print_admission_assessment/{id}/{context}','print_admission_assessment')->name('patient.print_admission_assessment');

        Route::get('/nursing/dashboard','nursing_dashboard')->name('nursing.dashboard');

        Route::get('clinical/nursing-notes/{vid?}/{t_id?}','nursing_notes')->name('clinical.nursing_notes');
        Route::get('clinical/nursing-vitals/{vid?}/{t_id?}','nursing_vitals')->name('clinical.nursing_vitals');
        // IPD Treatment Tracking (Medication Administration Record / e-MAR)
        Route::get('clinical/treatment-tracking/flowsheet/{id?}', [App\Http\Controllers\Clinical\TreatmentTrackingController::class, 'loadFlowsheet'])->name('clinical.treatment_tracking.flowsheet');
        Route::get('clinical/treatment-tracking/{vid?}/{t_id?}', [App\Http\Controllers\Clinical\TreatmentTrackingController::class, 'index'])->where('vid', '[0-9]+')->name('clinical.treatment_tracking');
        Route::post('clinical/treatment-tracking/administer-slot', [App\Http\Controllers\Clinical\TreatmentTrackingController::class, 'administerSlot'])->name('clinical.treatment_tracking.administer_slot');
        Route::post('clinical/treatment-tracking/dual-sign', [App\Http\Controllers\Clinical\TreatmentTrackingController::class, 'dualSignWitness'])->name('clinical.treatment_tracking.dual_sign');
        Route::post('clinical/treatment-tracking/administer-prn', [App\Http\Controllers\Clinical\TreatmentTrackingController::class, 'administerPrn'])->name('clinical.treatment_tracking.administer_prn');
        Route::post('clinical/treatment-tracking/execute-stat', [App\Http\Controllers\Clinical\TreatmentTrackingController::class, 'executeStat'])->name('clinical.treatment_tracking.execute_stat');
        Route::post('clinical/treatment-tracking/log-infusion', [App\Http\Controllers\Clinical\TreatmentTrackingController::class, 'logInfusionTitration'])->name('clinical.treatment_tracking.log_infusion');
        Route::post('clinical/treatment-tracking/hang-new-bag', [App\Http\Controllers\Clinical\TreatmentTrackingController::class, 'hangNewInfusionBag'])->name('clinical.treatment_tracking.hang_new_bag');
        Route::post('clinical/treatment-tracking/hold-infusion', [App\Http\Controllers\Clinical\TreatmentTrackingController::class, 'holdInfusion'])->name('clinical.treatment_tracking.hold_infusion');
        Route::post('clinical/treatment-tracking/resume-infusion', [App\Http\Controllers\Clinical\TreatmentTrackingController::class, 'resumeInfusion'])->name('clinical.treatment_tracking.resume_infusion');
        Route::get('clinical/treatment-tracking/infusion-alerts/{id}', [App\Http\Controllers\Clinical\TreatmentTrackingController::class, 'getInfusionAlerts'])->name('clinical.treatment_tracking.infusion_alerts');
        Route::post('clinical/treatment-tracking/verbal-countersign', [App\Http\Controllers\Clinical\TreatmentTrackingController::class, 'countersignVerbalOrder'])->name('clinical.treatment_tracking.verbal_countersign');
        Route::post('clinical/treatment-tracking/discontinue-order', [App\Http\Controllers\Clinical\TreatmentTrackingController::class, 'discontinueOrder'])->name('clinical.treatment_tracking.discontinue_order');
        Route::post('clinical/treatment-tracking/save-order', [App\Http\Controllers\Clinical\TreatmentTrackingController::class, 'saveNewOrder'])->name('clinical.treatment_tracking.save_order');
        Route::post('clinical/treatment-tracking/replace-order', [App\Http\Controllers\Clinical\TreatmentTrackingController::class, 'replaceOrder'])->name('clinical.treatment_tracking.replace_order');
        Route::post('clinical/treatment-tracking/change-frequency', [App\Http\Controllers\Clinical\TreatmentTrackingController::class, 'changeFrequency'])->name('clinical.treatment_tracking.change_frequency');
        Route::post('clinical/treatment-tracking/switch-route', [App\Http\Controllers\Clinical\TreatmentTrackingController::class, 'switchRoute'])->name('clinical.treatment_tracking.switch_route');
        Route::post('clinical/treatment-tracking/save-taper-protocol', [App\Http\Controllers\Clinical\TreatmentTrackingController::class, 'saveTaperProtocol'])->name('clinical.treatment_tracking.save_taper_protocol');
        Route::get('clinical/treatment-tracking/print/{id}', [App\Http\Controllers\Clinical\TreatmentTrackingController::class, 'printMedicineChart'])->name('clinical.treatment_tracking.print');
        Route::get('clinical/print-nursing-vitals/{id}','print_nursing_vitals')->name('clinical.print_nursing_vitals');
        Route::get('/ipd/add-nurse-new-note','add_nurse_new_note')->name('ipd.add_nurse_new_note');
        Route::get('/ipd-load-notes','load_ipd_notes')->name('ipd.load_notes');


        Route::post('/freetext/save-as-template','freetext_save_as_template')->name('freetext.save_as_template');
        Route::get('freetext/search-templates','search_templates')->name('freetext.search_templates');
        Route::post('prescription/save-as-template','prescription_save_as_template')->name('prescription.save_as_template');
        Route::get('/prescription/search-templates','prescription_search_templates')->name('prescription.search_templates');
        Route::get('/prescription/load-template','prescription_template')->name('prescription.load_template');
        Route::get('/load-template','load_template')->name('clinical.load_template');
        Route::post('patient/load_past_visit_medicine','load_past_visit_medicine')->name('patient.load_past_visit_medicine');
        Route::post('prescription/save-as-template2','prescription_save_as_template2')->name('prescription.save_as_template2');

        Route::post('patient/save-as-template','save_as_template')->name('patient.save_as_template');

        Route::get('patient/urology_opd_notes','urology_opd_notes')->name('patient.urology_opd_notes');
        Route::get('print/prescription/{id}','print_prescription_eros')->name('patient.print_prescription_eros');
        Route::get('patient/get_comments','get_comments')->name('patient.get_comments');
        Route::get('patient/gatepass','gatepass')->name('patient.gatepass');

        Route::get('patient/fetch_clinical_visit_list_status_for_any_context','fetch_clinical_visit_list_status_for_any_context')->name('patient.fetch_clinical_visit_list_status_for_any_context');

        // TESTING CANVAS PRESCRIPTION WRITING
        Route::get('patient/open-prescription/{vid?}/{t_id?}','open_prescription')->name('patient.open_prescription');
        Route::get('patient/fetch_ipd_visit_list','fetch_ipd_visit_list')->name('patient.fetch_ipd_visit_list');
        Route::get('patient/fetch_clinical_visit_list','fetch_clinical_visit_list')->name('patient.fetch_clinical_visit_list');
        Route::get('patient/fetch_visit_data_v2','fetch_visit_data_v2')->name('patient.fetch_visit_data_v2');
        Route::get('patient/fetch_clinical_comments','fetch_clinical_comments')->name('patient.fetch_clinical_comments');
        Route::get('patient/fetch_clinical_comments_by_context','fetch_clinical_comments_by_context')->name('patient.fetch_clinical_comments_by_context');
        Route::get('patient/fetch_clinical_comments_diagnosis','fetch_clinical_comments_diagnosis')->name('patient.fetch_clinical_comments_diagnosis');
        Route::get('patient/prescription/{vid?}/{t_id?}','prescription')->name('patient.prescription');
        Route::post('save-clinical-prescription-notes','save_clinical_prescription_notes')->name('patient.save_clinical_prescription_notes');
        Route::get('print-open-prescription/{id}','print_open_prescription')->name('patient.print_open_prescription');
        Route::get('print-open-prescription2/{id}','print_open_prescription2')->name('patient.print_open_prescription2');
        Route::post('/load-past-visit','load_past_visit_data')->name('load.past_visit_data');
        Route::get('/patient/visit-logs/{vid}/{context}','load_visit_logs')->name('patient.load_visit_logs');
        Route::post('send-open-prescription','send_open_prescription')->name('patient.send_open_prescription');
        Route::post('/copy-current-doc', 'copyCurrentDoc')->name('copy.current.doc');

        // TESTING CANVAS PRESCRIPTION WRITING
        Route::get('patient/handwrite-prescription/{vid?}/{t_id?}','handwrite_prescription')->name('patient.handwrite_prescription');
        Route::get('patient/handwrite-prescription-v2/{vid?}/{t_id?}','handwrite_prescription_v2')->name('patient.handwrite_prescription_v2');
        // Route::post('save-clinical-prescription-notes','save_clinical_prescription_notes')->name('patient.save_clinical_prescription_notes');
        // Route::get('patient/prescription/{vid?}/{t_id?}','prescription')->name('patient.prescription');
        // Route::post('save-clinical-prescription-notes','save_clinical_prescription_notes')->name('patient.save_clinical_prescription_notes');
        // Route::get('patient/print-canvas-prescription/{id}','print_canvas_prescription')->name('patient.print_canvas_prescription');

        // TESING ROUTE ENDS
        Route::get('clinical/form-builder','form_builder')->name('clinical.form_builder');
        Route::get('clinical/fetch-examine-note','fetch_examine_note')->name('clinical.fetch_examine_note');
        Route::get('clinical/clone-examine-note','clone_examine_note')->name('clinical.clone_examine_note');
        Route::get('clinical/delete-examine-note','delete_examine_note')->name('clinical.delete_examine_note');
        Route::get('clinical/fetch-examine-note-create','fetch_examine_note_create')->name('clinical.fetch_examine_note_create');
        Route::post('clinical/save-examine-note-create','save_examine_note_create')->name('clinical.save_examine_note_create');
        Route::get('clinical/render-examine-note','examine_note_render')->name('clinical.examine_note_render');
        Route::get('clinical/hip-examination/{vid?}/{t_id?}','hip_examination')->name('clinical.hip_examination');
        Route::get('clinical/render-examine-note-section','render_examine_note_section')->name('clinical.render_examine_note_section');
        Route::get('clinical/fetch-examine-note-section-edit','fetch_examine_note_section_edit')->name('clinical.fetch_examine_note_section_edit');
        Route::post('clinical/save-examine-note-section-edit','save_examine_note_section_edit')->name('clinical.save_examine_note_section_edit');

        Route::post('patient/urology/save','urology_save')->name('patient.urology_save');
        Route::get('patient/print_pres_urology/{id}','print_pres_urology')->name('patient.print_pres_urology');
        Route::get('patient/print_pres_gynea/{id}','print_pres_gynea')->name('patient.print_pres_gynea');
      //  Route::get('patient/print-priscription/{id}','print_prescription')->name('patient.print_prescription');

        Route::get('patient/ortho-clinical-notes/{vid?}/{t_id?}','ortho_clinical_notes')->name('patient.ortho_clinical_notes')->defaults('context', 'ortho_clinical_notes');
        Route::get('patient/copy-clinical-notes','copy_clinical_notes')->name('patient.copy_clinical_notes');

        Route::get('patient/ortho-clinical-notes-v3/{vid?}/{t_id?}','ortho_clinical_notes_v3')->name('patient.ortho_clinical_notes.v3');


        Route::get('patient/discharge_summary_v2/{vid?}/{t_id?}','discharge_summary_v2')->name('patient.discharge_summary_v2');
        Route::get('patient/discharge_summary_v3','discharge_summary_v3')->name('patient.discharge_summary_v3');
        Route::get('patient/discharge_summary_v4/{vid}/{t_id?}','ipd_discharge_summary')->name('patient.discharge_summary_v4');
        Route::get('patient/discharge_summary_v5/{vid}/{t_id?}','discharge_summary_v5')->name('patient.discharge_summary_v5');

        Route::get('fetch-patient-investigations','fetch_patient_investigations')->name('fetch_patient_investigations');
        Route::get('fetch-patient-visit-investigations-for-note','fetch_patient_investigations_for_notes')->name('fetch_patient_investigations_for_notes');
        Route::post('save-investigations','save_investigation_v3')->name('save.investigation.v3');

        // for eros
        Route::get('patient/discharge_summary_v6/{vid}/{t_id?}','ipd_discharge_summary_v6')->name('patient.discharge_summary_v6');

        Route::post('save_ipd_discharge_summary','save_ipd_discharge_summary')->name('patient.save_ipd_discharge_summary');
        Route::post('save-clinical-notes','save_clinical_notes')->name('patient.save_clinical_notes');
        Route::get('patient/clinical_notes','clinical_notes')->name('patient.clinical_notes');

        Route::post('/ipd-save-comments','save_ipd_comments')->name('ipd.save_comments');

        Route::get('/query_for_clinical_comments','query_for_clinical_comments')->name('query_for_clinical_comments');


        Route::post('/save-as-template2','save_as_template2')->name('patient.save_as_template2');
        Route::post('/autocomplete','autocomplete')->name('ipd.autocomplete');
        Route::post('/copy-note','copy_note')->name('ipd.copy_note');

        Route::get('get-ckd-stagging-result','get_ckd_stag')->name('patient.get_ckd_stag');

        Route::get('patient-get-investigations-history-layout','investigation_history_layout')->name('patient.investigation_history_layout');
        Route::get('patient-get-investigations-preview','investigation_preview')->name('patient.investigation_preview');
        Route::get('get-investigations-result-with-codes','get_investigations_result_with_codes')->name('get_investigations_result_with_codes');

        Route::get('patient-get-investigations-tracking','investigation_history_tracking')->name('patient.investigation_history_tracking');

        Route::get('patient-get-investigations-tracking-setup','investigation_history_tracking_setup')->name('patient.investigation_history_tracking_setup');
        Route::post('save-patient-investigations-tracking-setup','save_investigation_tracking_setup')->name('patient.save_investigation_tracking_setup');
        Route::get('remove-patient-investigations-tracking-item','remove_investigation_tracking_setup')->name('remove_investigation_tracking_setup');

        Route::get('spec-get-investigations-tracking-setup','spec_investigation_history_tracking_setup')->name('spec.investigation_history_tracking_setup');
        Route::post('save-spec-investigations-tracking-setup','spec_save_investigation_tracking_setup')->name('spec.save_investigation_tracking_setup');
        Route::get('remove-spec-investigations-tracking-item','spec_remove_investigation_tracking_setup')->name('remove_spec_investigation_tracking_setup');

        Route::post('patient/save_ipd_notes','save_ipd_notes')->name('patient.save_ipd_notes');
        Route::post('patient/save_nursing_notes','save_nursing_notes')->name('patient.save_nursing_notes');

        Route::get('update_context','update_context')->name('update_context');

        Route::post('save_clinical_template','save_clinical_template')->name('save_clinical_template');
        Route::post('lockClinicalNotes','lockClinicalNotes')->name('lockClinicalNotes');
        Route::post('patient/save_freetext2','save_freetext2')->name('patient.save_freetext2');
        Route::get('patient/print_clinical_notes/{vid}','print_clinical_notes')->name('print_clinical_notes');

        Route::get('patient/discharge_summary/{vid}','opd_patient_discharge')->name('patient.discharge_summary');
        Route::post('opd_patient_discharge/save','saveopddischarge')->name('patient.save_discharge');
        Route::get('opd_discharge_print/{vid}','opd_discharge_print')->name('patient.opd_discharge_print');

        Route::get('medicine/brand/{id}/{source}','medicine_brands')->name('medicine.brands');
        Route::post('vaccine/save','vaccine_save')->name('vaccine.save');

        Route::get('patient/vitals_list','vitals_list')->name('patient.vitals_list');
        Route::post('patient/save_pulse','save_pulse')->name('patient.save_pulse');
        Route::post('patient/save-vital-log', 'saveVitalLog')->name('patient.save_vital_log');
        Route::post('patient/delete-vital-log', 'deleteVitalLog')->name('patient.delete_vital_log');
        Route::post('clinical/save-triage', 'saveTriage')->name('clinical.save_triage');
        Route::get('patient/pulse_list','vitals_pulse_list')->name('patient.pulse_list');
        Route::get('icd_code/seach','icd_search')->name('patient.icd_search');
        Route::post('patient/icd_save','icd_save')->name('patient.icd_save');
        Route::post('patient/save_diagnosis','save_diagnosis')->name('patient.save_diagnosis');
        Route::get('patient/icd_list','icd_list')->name('patient.icd_list');
        Route::post('patient/add_alerts','add_alert')->name('patient.add_alert');
        Route::get('patient/get_sticker_list','get_sticker_list')->name('patient.get_sticker_list');
        Route::post('patient/add_investigation','add_investigation')->name('patient.add_investigation');
        Route::get('patient/get_investigation_list','get_investigation_list')->name('patient.get_investigation_list');
        Route::post('patient/delete_investigation','delete_investigation')->name('patient.delete_investigation');
        Route::post('patient/delete_alert','delete_alert')->name('patient.delete_alert');
        Route::post('patient/delete_icd','delete_icd')->name('patient.delete_icd');
        Route::post('patient/physical_examination','physical_examination')->name('patient.physical_examination');
        Route::get('patient/get_instructions','get_instructions')->name('patient.get_instructions');
        Route::get('patient/previous_comment','previous_comment')->name('patient.previous_comment');
        Route::get('patient/get_composition','get_composition')->name('patient.get_composition');
        Route::get('prescription/instructions','instructions')->name('prescription.instructions');
        Route::get('prescription/single-instructions','instructions_single')->name('prescription.instructions_single');

        Route::get('patient/gyneacology-clinical-notes/{vid?}/{t_id?}','gynea_clinical_notes')->name('patient.gynea_clinical_notes');
        Route::post('patient/show_logs','show_logs')->name('patient.show_logs');


        Route::get('patient/get-clinical-info','get_patient_clinical_section')->name('patient.get_patient_clinical_section');
        Route::get('patient/get-medical-history-section-data','medical_history_form_section_data')->name('patient.medical_history_form_section_data');
        Route::get('patient/get-medical-history-form','medical_history_form')->name('patient.medical_history_form');
        Route::match(['get', 'post'], 'patient/save-medical-history','add_medical_history')->name('patient.add_medical_history');
        Route::match(['get', 'post'], 'patient/remove-medical-history','remove_medical_history')->name('patient.remove_medical_history');


        Route::get('settle-bill-view','settle_bill_view')->name('settle.bill_view');
        Route::post('settle-all-paid-bills','settle_all_paid_bills')->name('patient.settle_all_bills');
        Route::post('lock-all-bills','lock_all_bills')->name('patient.lock_all_bills');


        Route::get('getcharges/{iid}/{sid}','get_charges')->name('getcharges');
        Route::get('getconsultcharges/{docId}/{uhid?}','get_consultcharges')->name('getconsultcharges');
        Route::get('get_reg_opd_services/{uhid}','get_reg_opd_services')->name('get_reg_opd_services');
        Route::get('getservices/{iid}','get_services')->name('getservices');
        Route::post('patient/startbill','patient_startbill')->name('patient.startbill');
        Route::post('patient/patient_patStartbill','patient_patStartbill')->name('patient.patient_patStartbill');
        Route::get('getcharges_details/{sid}/{iid}/{billid}','getcharges_details')->name('getcharges.details');
        Route::get('getmischarges_details/{sid}/{billid}','getmischarges_details')->name('getmischarges.details');
        Route::get('get-discount/{selectedDiscountid}','getDiscounts')->name('getDiscounts');
        Route::get('getchargesbyid/{docId}','get_chargesbyid')->name('getchargesbyid');
        Route::post('patient/genbill','gen_bill')->name('patient.genbill');
        Route::post('patient/send_whatsapp_gen_bill','send_wp_gen_bill')->name('patient.send_wp_gen_bill');
        Route::post('patient/ipdgenbill','ipd_gen_bill')->name('patient.ipdgenbill');
        Route::post('visit/save','visit_save')->name('visit.save');
        Route::get('visit/list','visit_list')->name('visit.list');
        Route::get('visit/clinical/list','clinical_visit_list')->name('visit.clinical.list');
        Route::get('visit/followups','visit_list_followups')->name('visit.followups_list');
        Route::get('visit/followups-search','visit_list_followups_search')->name('visit.followups_list_search');
        Route::get('export/visit_list','export_visit_list')->name('export.visit_list');
        Route::post('send-follow-up-message','send_follow_up_message')->name('visit.send_follow_up_message');
        Route::get('visit/patient_list','visit_patientList')->name('visit.patientlist');
        Route::get('visit/add','visit_add')->name('visit.add');
        Route::get('bill_list/{id}','bill_list')->name('visit.bill_list');
        Route::get('patient/all/bill_list/{id}','patient_all_bill_list')->name('all.visit.bill_list');
        Route::get('show_bill/{id}','bill_show')->name('visit.bill_show')->defaults('context', 'print_bill');
        Route::get('show_bill-v2/{id}','bill_show_v2')->name('visit.bill_show.v2')->defaults('context', 'print_bill');
        Route::get('show_bill_payment_receipt/{id}','show_bill_payment_receipt')->name('visit.bill_payment_receipt_show');
        Route::post('visit_filter','visit_filter')->name('visit.filter');
        Route::get('get/patient/details','getPatientDetails')->name('get.patient.details');
        Route::post('visit/patsave','visit_patsave')->name('visit.patsave');
        Route::get('print_bill/{id}', 'print_bill')->name('print_bill');
        Route::get('print_combine_bill', 'print_combine_bill')->name('print_combine_bill');
        Route::post('patient/cancel-visit','cancel_visit')->name('patient.cancel_visit');
        Route::get('show_bill_with_cancelled/{id}','bill_show_with_cancelled')->name('visit.bill_show.cancelled');

        Route::post('patient/admit','admit_patient')->name('patient.admit_patient');

        Route::post('patient/add_ref_list','add_ref_list')->name('patient.add_ref_list');

        Route::get('patient/draft','patient_draft')->name('patient.draft');
        Route::post('patient/save_as_draft','save_as_draft')->name('patient.save_as_draft');

        Route::get('getConsultChargeById/{chargeId}','getConsultChargeById')->name('getConsultChargeById');
        Route::get('patient/visit_list','patient_visit_list')->name('patient.patient_visit_list');
        Route::get('patient/er_visit_list','er_visit_list')->name('patient.er.visit_list');

        Route::get('patient/update_visit_on_hold_status','update_visit_on_hold_status')->name('patient.update_visit_on_hold_status');
        Route::get('patient/update_visit_emergency_status','update_visit_emergency_status')->name('patient.update_visit_emergency_status');
        Route::get('patient/fetch_today_patient_visit_charges','fetch_today_patient_visit_charges')->name('patient.fetch_today_patient_visit_charges');
        Route::get('patient/fetch_er_patient_visit_charges','fetch_er_patient_visit_charges')->name('patient.fetch_er_patient_visit_charges');

        Route::get('patient/fetch_today_patient_visit_charges_v5','fetch_today_patient_visit_charges_v5')->name('patient.fetch_today_patient_visit_charges_v5');
        Route::post('patient/service-status/update','updateServiceStatus')->name('patient.service.status.update');
        Route::post('patient/ordered-service-status-update','updateOrderedServiceStatus')->name('patient.ordered_service_status_update');

        Route::get('patient/update_visit_status','update_visit_status')->name('patient.update_visit_status');

        Route::get('vital_visit_list','vital_visit_list')->name('vital_visit.list');
        Route::get('vital/add/{id}/{patient_id}/{doctor_id}','vital_add')->name('vital.add');
        Route::post('vital/save','vital_save')->name('vital.save');
        Route::post('patient/save_vital','save_vital')->name('patient.save_vital');
        Route::get('vital-form','vital_form')->name('patient.update_vital_form');

        Route::get('opd_slip/{pid}/{sid}','opd_slip')->name('opd_slip');
        Route::get('print_patient_info_slip','print_patient_info_slip')->name('print_patient_info_slip');

        Route::get('print_opd_slip','print_opd_slip')->name('print_opd_slip');
        Route::get('print_patient_info_slip','print_patient_info_slip')->name('print_patient_info_slip');
        Route::get('download_opd_slip','download_opd_slip')->name('download_opd_slip');
        Route::get('gen_receipt_no','generate_lost_receipt_no')->name('gen_receipt_no');

        Route::get('/get-cities',  'getCities')->name('get.cities');
        Route::get('add-city',  'add_city')->name('add_city');
        Route::post('save-city',  'save_city')->name('save.city');
        Route::post('approve-city',  'approve_city')->name('approve.city');
        Route::post('update-city','update_city')->name('update.city');

        Route::get('/get-ref-doctor',  'get_ref_doctor')->name('get.ref_doctor');
        Route::get('add-ref-doctor',  'add_ref_doctor')->name('add_ref_doctor');
        Route::post('save-ref-doctor',  'save_ref_doctor')->name('save.ref_doctor');
        Route::post('approve-ref-doctor',  'approve_ref_doctor')->name('approve.ref_doctor');
        Route::post('update/ref-doctor','update_ref_doctor')->name('update.ref_doctor');


        // open old bill
        // ***********************   BILLs ROUTES *****************************
        Route::get('/all-bills', 'all_bill_list')->name('bills.list');
        Route::get('/all-bills-v1', 'all_bill_list_v1')->name('bills.list.v1');
        Route::get('/all-bills-v2', 'all_bill_list_v2')->name('bills.list.v2');
        Route::get('/all-receipts', 'all_receipt_list')->name('receipts.list');
        Route::post('/update-payment-receipt', 'update_payment_receipt')->name('receipts.update_payment');
        Route::get('/opd-bills','all_bill_list')->name('bills.opd_list');
        Route::get('/cancel-bill-items/{id}/{req_id?}', 'cancel_bill_item')->name('bills.cancel_items');
        Route::get('/bill-discount-and-refund/{id}/{req_id?}', 'discount_and_refund')->name('bills.discount_and_refund');
        Route::post('/save-cancel-bill-items', 'save_cancel_bill_item')->name('bills.save_cancel_bill_items');
        Route::post('/edit-bill-items', 'edit_bill_item')->name('bills.edit');
        Route::get('open_old_bill','open_old_bill')->name('open_old_bill');
        Route::post('save_edited_bill','save_edited_bill')->name('save_edited_bill');
        Route::POST('patient/edit-bill','load_edit_bill')->name('load.bill.edit');

        Route::post('/save_discount_and_refund', 'save_discount_and_refund')->name('save_discount_and_refund');

        Route::POST('mark-bill-cancel','mark_bills_cancel')->name('mark.bills.cancel');
        Route::get('patient/get-fud','get_fud')->name('patient.get_fud');
        Route::get('patient-get-fud2','get_fud_v2')->name('patient.get_fud_v2');

        Route::post('send-cancel-request','send_cancel_request')->name('send-cancel-request');
        Route::post('update_status_cancel_req','update_status_cancel_req')->name('update_status_cancel_req');


        Route::get('/all-bill-list-for-settlement', 'loadAllBills')->name('bills.all');
        Route::get('/bills/settled','loadSettleBills')->name('bills.settled');
        Route::get('/load-bill-list-for-settlement', 'loadSettledBills')->name('bills.fully_paid');
        Route::get('/bills/unsettled','loadUnsettledBills')->name('bills.partial_paid');
        Route::post('/patient/mark-bill-as-settled', 'mark_bills_as_settled')->name('patient.mark_bills_as_settled');
        Route::post('/patient/settle-bill-services/{id}', 'settle_bill_services')->name('settle_bill_services');
        Route::POST('bill/unsettle','unsettle_bill')->name('patient.bill_unsettle_mark');


        // ***********************   REFUND ROUTES *****************************
        Route::get('initiate-refund/{id?}/{billId?}','initiate_refund')->name('initiate_refund');
        Route::post('save-initiate-refund/{id}','save_initiate_refund')->name('save.initiate_refund');
        Route::post('update-initiate-refund/{id}','update_initiate_refund')->name('update.initiate_refund');
        Route::get('refund-list','refund_list')->name('list.refund');
        Route::match(['get', 'post'], 'approve-refund/{id}','approve_refund')->name('approve.refund');
        Route::match(['get', 'post'], 'issue-refund/{id}','issue_refund')->name('issue.refund');
        Route::match(['get', 'post'], 'reject-refund/{id}','reject_refund')->name('reject.refund');
        Route::get('view-refund','view_refund')->name('view.refund');
        Route::get('print-refund-bill','print_refund_bill')->name('print.refund_bill');

        // ************************* Bill settlement Routes ****************************
        Route::get('bill-settlement/{bill_id}','initiate_bill_settlement')->name('patient.bill_settlement');
        Route::post('save-bill-settlement','save_bill_settlement')->name('patient.save_bill_settlement');
        Route::get('unsettled-bill-list','unsettled_bill_list')->name('patient.ubsettled_bill');
        Route::get('view-outstanding-bill','view_outstanding_bill')->name('view.outstanding_bill');
        Route::get('get-all-billing','get_all_billing')->name('patient.all_bills');
        Route::get('fetch-all-billing','fetch_all_billing')->name('patient.fetch_all_bills');
        Route::get('view-bill_settlements','view_bill_settlements')->name('view.bill_settlements');
        Route::get('view-bill','view_bill')->name('patient.view_bill');

        Route::get('new-opd-billing','new_opd_billing')->name('new.opd_billing');
        Route::post('opd-save-billing','save_opd_billing')->name('opd.save_billing');
        Route::post('services-fetch','services_fetch')->name('services.fetch');
        Route::post('services-fetch-hospital','services_fetch_from_hospital')->name('services.fetch_hospital');

        Route::get('edit-opd-bill/{id}','edit_new_opd_bill')->name('edit.opd_billing');

        // ***********************   LAB TEST ROUTES *****************************
        Route::middleware(['module-license:lab_investigation'])->group(function () {

          Route::get('lab-dashboard','lab_dashboard')->name('lab_test.dashboard');
          Route::get('lab-dashboard-month-chart-data','lab_dashboard_month_chart_data')->name('lab_test.month_chart_data');

          Route::get('lab-test-orders','lab_test_orders')->name('lab_test.orders');
          Route::get('lab-test-orders-outsource','lab_test_orders_outsource')->name('lab_test.orders_outsource');
          Route::get('lab-test-outsource-tracking','lab_test_outsource_tracking')->name('lab_test.outsource_tracking');
          Route::get('outsource-lab-tracking','lab_test_outsource_tracking');
          Route::post('lab-test-update-follow-up-date','update_follow_up_date')->name('lab_report.update_follow_up_date');
          Route::get('lab-test-follow-up-modal','get_follow_up_modal')->name('lab_test.get_follow_up_modal');
          Route::get('lab-test-report-modal','get_report_modal')->name('lab_test.get_report_modal');
          Route::get('lab-test-follow-up-report-modal','get_follow_up_report_modal')->name('lab_test.get_follow_up_report_modal');
          Route::post('lab-test-delete-outsource-report','delete_outsource_order_report')->name('lab_test.delete_outsource_order_report');

          Route::get('lab-test-bill-wise','lab_test_orders_bill_wise')->name('lab_test.orders_bill_wise');
          Route::post('lab-test-bill-wise/upload','lab_bill_wise_upload_report')->name('lab_test.bill_wise_upload_report');
          Route::get('lab-test-bill-wise/view/{id}','lab_bill_wise_view_report')->name('lab_test.bill_wise_view_report');
          Route::post('lab-test-bill-wise/delete','lab_bill_wise_delete_report')->name('lab_test.bill_wise_delete_report');
          Route::post('lab-test-bill-wise/follow-up','lab_bill_wise_save_follow_up')->name('lab_test.bill_wise_save_follow_up');
          Route::post('lab-test-bill-wise/notification/mark-read','lab_bill_wise_mark_notification_read')->name('lab_test.bill_wise_mark_notification_read');

          Route::get('lab-test-item-consumption-mapping', 'test_item_consumption_mapping_index')->name('lab_test.item_consumption_mapping');
          Route::post('lab-test-item-consumption-mapping/save', 'save_test_item_consumption_mapping')->name('lab_test.save_item_consumption_mapping');
          Route::get('lab-test-item-consumption-mapping/search-items', 'search_hospital_items')->name('lab_test.search_hospital_items');
          Route::get('lab-test-get-sample-collection-form','lab_test_get_sample_collection_form')->name('lab_test.collect_sample_form');
          Route::POST('lab-test-generate-sample-ids','generate_lab_test_sample_ids')->name('lab_test.generate_lab_test_sample_ids');
          Route::POST('lab-test-generate-sample-id','generate_test_sample_id_v2')->name('lab_test.generate_test_sample_id_v2');
          Route::POST('lab-test-generate-sample-ids-for-all','generate_test_sample_id_for_all_v2')->name('lab_test.generate_test_sample_id_for_all_v2');
          Route::post('lab-test-save-sample','lab_test_save_sample')->name('lab_test.collect_sample');
          Route::post('lab-test-update-sample-status','lab_test_update_sample_status')->name('lab_test.update_sample_status');
          Route::get('lab-test-update-test-in-process-status','updated_order_in_process_status')->name('lab_test.updated_order_in_process_status');
          Route::get('lab-test-upload/{id}','lab_test_upload')->name('lab_test.upload');
          Route::get('lab-test-report-upload','lab_test_report_upload')->name('lab_test.report_upload');
          Route::post('lab-test-save','lab_test_save')->name('lab_test.save');
          Route::post('lab-test-save2','lab_test_save2')->name('lab_test.save2');
          Route::get('lab-test-edit/{id}','lab_test_edit_page')->name('lab_test.edit');
          Route::post('lab-test-update','lab_test_update')->name('lab_test.update');
          Route::get('get-lab-test-for-approve','get_lab_test_approve_page')->name('lab_test.get_approve_page');
          Route::get('lab-test-approve/{id}','lab_test_approve_page')->name('lab_test.approve_page');
          Route::post('lab-test-approve-save','lab_test_approve')->name('lab_test.approve');
          Route::get('print-test-report','lab_test_report_show_print')->name('lab_test.report_print');
          Route::get('print-test-report2','lab_test_report_show_print2')->name('lab_test.report_print2');
          Route::get('bulk-print-test-report','lab_test_report_print_bulk')->name('lab_test.report_print_bulk');
          Route::post('print-sample-label','lab_test_sample_label_print')->name('lab_test.sample_label_print');
          Route::get('pending-lab-test-report','pending_lab_test_report')->name('lab_report.pending_report');
          Route::get('completed-lab-test-report','completed_lab_test_report')->name('lab_report.completed_report');
          Route::post('send-for-approval','lab_report_send_for_approval')->name('lab_report.send_for_approval');
          Route::post('disapprove-test','lab_test_disapprove')->name('lab_report.disapprove_test');

          //V3
          Route::get('lab-sample-collection','lab_sample_collection')->name('lab_sample_collection');
          Route::post('generate-test-sample-ids','generate_test_samples_id_v3')->name('lab_test.generate_test_samples_id_v3');
          Route::post('update-lab-sample-status','update_lab_sample_status_v3')->name('lab_test.update_lab_sample_status_v3');
          Route::get('print-sample-label-v3','sample_label_print_v3')->name('lab_test.sample_label_print_v3');
          Route::get('get-sample-history-for-patient','get_lab_sample_history')->name('lab_test.get_lab_sample_history');
          Route::get('pick-lab-sample-for-rerun','pick_sample_for_rerun')->name('lab_test.pick_sample_for_rerun');

          //LAB GROUPS
          Route::post('save-lab-sample-group','save_sample_lab_sample_group')->name('lab_test.save_sample_lab_sample_group');
          Route::post('save-lab-sample-group-service','save_sample_lab_sample_group_service_index')->name('lab_test.save_sample_lab_sample_group_service_index');

          Route::get('lab-report-show','lab_test_report_show')->name('show.lab_test_pdf');
          Route::get('lab-reports-show','lab_test_report_show_bulk')->name('bulkshow.lab_test_pdf');
          Route::post('lab-reports-send-whatsapp','lab_test_send_whatsapp')->name('send_whatsappp.lab_test');


          Route::get('copy-tests-to-ipd','copy_tests_to_ipd')->name('show.copy_test_orders');
          Route::post('save-tests-to-ipd','save_tests_to_ipd')->name('save.copy_test_orders');

          Route::get('test-report/{id}','test_report')->name('test_report');
          Route::get('fetch-lis-logs','fetch_lis_logs')->name('lab_test.fetch_lis_logs');
          Route::get('fetch-lis-logs-v3','fetch_lis_logs_v3')->name('lab_test.fetch_lis_logs_v3');
          Route::get('copy-lis-logs/{id}/{oid}','copy_lis_report')->name('lab_test.copy_lis_report');
          Route::get('patient/get-lis-loinc-mapping-modal','get_lis_loinc_mapping_modal')->name('patient.get_lis_loinc_mapping_modal');
          Route::post('patient/save-lis-loinc-mapping','save_lis_loinc_mapping')->name('patient.save_lis_loinc_mapping');
          Route::get('hl7-parsing','hl_parsing')->name('hl_parsing');

          // Billing Routes
          Route::get('lab-billing','lab_billing')->name('lab.billing');

          /* Patient Follow up */
          Route::get('patient/follow_up','patientFollowUp')->name('patient_follow_up');


           Route::get('add-new-medicine',[App\Http\Controllers\DrugsDBController::class,'add_new_medicine'])->name('add.new.medicine');
           Route::post('save-new-medicine',[App\Http\Controllers\DrugsDBController::class,'save_new_medicine'])->name('save.new.medicine');
        });

        // ***********************  PROCEDURE ORDERS ROUTES *****************************
        Route::prefix('procedure')->group(function () {
          Route::get('orders','procedures_orders')->name('procedure.orders');
          Route::get('fetch-procedure-orders','fetch_procedure_orders')->name('procedures.fetch_orders');
          Route::get('get-procedure-assign-staff','get_procedure_assign_staff')->name('procedures.get_assign_staff');
          Route::get('print-procedure-supporting-doc','print_procedure_supporting_doc')->name('procedures.print_procedure_supporting_doc');
          Route::post('save-procedure-assign-staff','save_procedure_assign_staff')->name('procedures.save_assign_staff');
          Route::post('remove-procedure-assign-staff','remove_procedure_assign_staff')->name('procedures.remove_assign_staff');
        });

        Route::get('advance_search','advance_search')->name('advance_search');
        Route::post('patient_advsearch','patient_advsearch')->name('patient.advsearch');
        Route::get('getpatient_details/{id}/{tid?}','getpatient_details')->name('getpatient_details');
        Route::get('/doctors-by-specialization/{spec_id?}',  'getDoctorsBySpecialization')->name('get.doctor.spec');
        Route::get('/doctor-name/{doc_id}',  'getDoctorName')->name('get.doctor.name');

        Route::get('patient-search','patient_search')->name('patient_search');

        Route::get('patient-visit-note-investigation','patient_visit_note_investigation')->name('patient.get_visit_note_investigation');
        Route::get('patient-visit-note-medicines', 'patient_visit_note_medicines')->name('patient.get_visit_note_medicines');
        Route::get('patient-visit-prescription-details/{visit_id}', 'patient_visit_prescription_details')->name('patient.get_visit_prescription_details');
        Route::get('get-item-details-and-batches', 'getItemDetailsAndBatches')->name('patient.get_item_details_and_batches');


        Route::get('vital_visit_add/{vid?}','vital_visit_add')->name('vital_visit.add');
        Route::post('vital_visit_save','vital_visit_save')->name('vital.visit.store');
        Route::post('options_store','options_store')->name('options.store');
        Route::get('complaints/search',  'search')->name('symptoms.search');
        Route::get('diagnosis/search',  'diagnosis_search')->name('diagnosis.search');
        Route::post('diag_options_store','diag_options_store')->name('diag.options.store');
        Route::get('test/search',  'test_search')->name('test.search');
        Route::get('/symptonlist',  'symptonlist')->name('symptonlist');
        Route::get('medicine/search',  'medicinesearch')->name('medicine.search');
        Route::get('medicine/search2',  'medicinesearch2')->name('medicine.search2');
        Route::get('medicine/search3',  'medicinesearch3')->name('medicine.search3');
        Route::get('medicine/search4',  'medicinesearch4')->name('medicine.search4');
        Route::post('save_medicine','save_medicine')->name('patient.save_medicine');
        Route::post('/end-consult', 'endConsult')->name('endConsult');



        Route::post('patient/save_visit_other','save_visit_other')->name('vital.visit_other.store');
        Route::post('patient/delete_medicine','delete_medicine')->name('patient.delete_medicine');

        Route::get('/get_patient_total_visit/{uhid}','get_patient_total_visit')->name('patient.total_visit');
        Route::get('/patient_visit_log/{uhid}','patient_visit_log')->name('patient.visit_log');
        Route::get('/patient_visit_log_with_doctor/{uhid}/{doctor_id}','patient_visit_log_with_doctor')->name('patient.visit_log_with_doctor');

        ################################## --- Copy Mars Clinical Data To empath --- ##################################
        Route::get('copy_other_diagnosis_data','copy_other_diagnosis_data')->name('copy_other_diagnosis_data');
        Route::get('copy_clinical_history_comment_data','copy_clinical_history_comment_data')->name('copy_clinical_history_comment_data');

        Route::get('patient/day_care_summary/{vid?}/{t_id?}','day_care_summary')->name('patient.day_care_summary');
        Route::get('print-day-care-summary/{id}','print_day_care_summary')->name('print.day_care_summary');

        Route::get('queue/manager','queue_manager')->name('queue.manager');
        Route::post('save/queue/manager','save_queue_manager')->name('save.queue.manager');
        Route::get('queue/doctor-opd-timing','doctor_opd_timing')->name('queue.doctor_opd_timing');


        Route::get('queue/generate-token','generate_queue_token')->name('queue.generate_token');

        Route::get('queue/manager/select-doctor','queue_manager_select')->name('queue.manager_select');
        Route::get('queue-doctor-manage','queue_doctor_manage')->name('queue.queue_doctor_manage');
        Route::get('update_opd_queue_status','update_opd_queue_status')->name('update_opd_queue_status');

        Route::get('/update/queue/{id}', 'update_queue')->name('update.queue');

        Route::get('show/opd_queue/{id}','opd_queue_show')->name('show.opd_queue');
        Route::get('/update_queue_status', 'update_queue_status')->name('admin.update_queue_status');
        Route::post('/update_doctor_opd_status', 'update_doctor_opd_status')->name('admin.update_doctor_opd_status');

        Route::get('patient/diet','patient_diet')->name('patient.diet');

        Route::get('patient/echo-lab/{id?}/{t_id?}','patient_echo')->name('patient.echo');
        Route::get('print/echo/{id}','print_echo')->name('patient.print_echo');


        // ***********************   Mobile version ROUTES *****************************
        Route::get('m-patient-register','m_patient_register')->name('mobile.patient.register');
        Route::post('m-patient-register-save','m_patient_register_save')->name('m.patient.register');
        Route::post('m-patient-create-visit','m_patient_create_visit')->name('m.patient.create_visit');
        Route::get('/camera-view', 'camera_viewer')->name('camera.view');
        Route::get('upcomming-appointments','upcomming_appointments')->name('upcomming.appointments');
        Route::get('m-edit-profile','m_edit_profile')->name('mobile.edit.profile');
        Route::post('m-update-profile','m_update_profile')->name('mobile.update.profile');
        Route::get('m-surgery-form','m_surgery_form')->name('patient.surgery_form');
        Route::post('m-surgery-save/{id}','m_surgery_save')->name('m.surgery.save');
        Route::get('m-surgery-edit/{id}','m_surgery_edit')->name('m.surgery.edit');
        Route::put('m-surgery-update/{id}','m_surgery_update')->name('m.surgery.update');
        Route::get('m-patient-alerts','m_patient_alerts')->name('patient.alerts');
        Route::post('save-patient-alerts/{vid}','save_patient_alerts')->name('save.patient.alerts');
        Route::post('update-patient-alerts/{vid}','update_patient_alerts')->name('update.patient.alerts');



        Route::get('pre-op-checklist','m_patient_checklist')->name('patient.pre_op.checklist');
        Route::post('save-pre-op-checklist/{id}','save_patient_checklist')->name('save.patient.checklist');
        Route::post('update-patient-checklist/{id}','update_patient_checklist')->name('update.patient.checklist');
        Route::get('m-hospital-setting','m_hospital_setting')->name('m.hospital.settings');
        Route::post('m-send-whatsapp-alert','send_whatsapp_alert')->name('send.whatsapp.alert');

        // Add Surgery
         Route::get('m-add-surgery','m_add_surgery')->name('mobile.add.surgery');
         Route::post('m-save-surgery','saveSurgery')->name('m.save.surgery');
         Route::get('get_service_details', 'getServiceDetails')->name('get_service_details');
         Route::post('/surgery/toggle-active', 'toggleActive')->name('surgery.toggleActive');
         Route::get('/surgery/{id}/edit', 'mEditService')->name('surgery.edit');
         Route::post('/surgery/update', 'mUpdateService')->name('surgery.update');

        Route::get('m-consent-upload','patient_upload_consent')->name('patient.consent.upload');
        Route::post('m-save-consent','save_patient_consent')->name('save.patient.consent');



        Route::get('discharge-summary-neonatal/{vid}/{t_id?}','ipd_discharge_summary_neonatal')->name('ipd_discharge_summary_neonatal');

    });

    // V2 Routes
    Route::prefix('v2/clinical')->name('v2.')->group(function () {

        Route::get('', [App\Http\Controllers\V2\ClinicalController::class, 'default_clinical_v2'])->name('clinical.entry');
        Route::get('entry/{vid?}/{t_id?}', [App\Http\Controllers\V2\ClinicalController::class, 'default_clinical_v2'])->name('clinical.entry.params');

    // Clinical Notes
        Route::get('notes', [App\Http\Controllers\V2\ClinicalController::class, 'default_clinical_v2'])->name('clinical-notes');

        Route::middleware(['module-license:opd_clinical.general_prescription'])->group(function () {
          Route::get('general/{vid?}/{t_id?}',[App\Http\Controllers\V2\ClinicalController::class, 'general_notes_v2'])->name('general.notes');
        });
        Route::middleware(['module-license:opd_clinical.cardiology'])->group(function () {
          Route::get('cardio/{vid?}/{t_id?}',[App\Http\Controllers\V2\ClinicalController::class, 'cardio_notes_v2'])->name('cardio.notes');
        });
        Route::middleware(['module-license:opd_clinical.dental'])->group(function () {
          Route::get('dental/{vid?}/{t_id?}',[App\Http\Controllers\V2\ClinicalController::class, 'dental_notes_v2'])->name('dental.notes');
        });
        Route::match(['get', 'post'], 'print/{id?}', [App\Http\Controllers\V2\ClinicalController::class, 'print_document'])->name('clinical.print');
        Route::post('ping-page-lock', [App\Http\Controllers\V2\ClinicalController::class, 'ping_page_lock'])->name('clinical.ping_lock');
        Route::post('takeover-page-lock', [App\Http\Controllers\V2\ClinicalController::class, 'takeover_page_lock'])->name('clinical.takeover_lock');
        Route::post('release-page-lock', [App\Http\Controllers\V2\ClinicalController::class, 'release_page_lock'])->name('clinical.release_lock');
        Route::post('save-named-template', [App\Http\Controllers\V2\ClinicalController::class, 'save_named_template'])->name('clinical.save_named_template');
        Route::post('delete-named-template', [App\Http\Controllers\V2\ClinicalController::class, 'delete_named_template'])->name('clinical.delete_named_template');
        Route::get('patient-vitals-logs/{patient_id}', [App\Http\Controllers\V2\ClinicalController::class, 'get_patient_vitals_logs'])->name('clinical.patient_vitals_logs');
        Route::get('doctor-protocols/{doctor_id}', [App\Http\Controllers\V2\ClinicalController::class, 'get_doctor_protocols'])->name('clinical.doctor_protocols');
        Route::get('doctor-leave-availability', [App\Http\Controllers\V2\ClinicalController::class, 'get_doctor_leave_availability'])->name('clinical.doctor_leave_availability');
        Route::get('patient-list-ajax', [App\Http\Controllers\V2\ClinicalController::class, 'get_patient_list_ajax'])->name('clinical.patient_list_ajax');
        Route::post('upload-clinical-image', [App\Http\Controllers\V2\ClinicalController::class, 'upload_clinical_image'])->name('clinical.upload_image');
        Route::get('brand-instructions', [App\Http\Controllers\V2\ClinicalController::class, 'get_brand_instructions'])->name('clinical.brand_instructions');

    });

    Route::group(['prefix'=>'v2/registration'],function(){
        Route::get('/', [App\Http\Controllers\RegistrationController::class, 'index'])->name('registration.index');
        Route::get('/search', [App\Http\Controllers\RegistrationController::class, 'search'])->name('registration.search');
        Route::get('/check-duplicate-mobile', [App\Http\Controllers\RegistrationController::class, 'checkDuplicateMobile'])->name('registration.check_duplicate_mobile');
        Route::get('/visits-today', [App\Http\Controllers\RegistrationController::class, 'getVisitsToday'])->name('registration.visits_today');
        Route::get('/patient-visits/{uhid}', [App\Http\Controllers\RegistrationController::class, 'getPatientVisits'])->name('registration.patient_visits');
        Route::get('/draft-bill/{billId}', [App\Http\Controllers\RegistrationController::class, 'getDraftBill'])->name('registration.draft_bill');
        Route::post('/save', [App\Http\Controllers\RegistrationController::class, 'save'])->name('registration.save');
    });

    Route::group(['prefix' => 'v2/billing'], function () {
        Route::get('/', [App\Http\Controllers\V2\BillingController::class, 'index'])->name('v2.billing.index');
        Route::get('/list', [App\Http\Controllers\V2\BillingController::class, 'listBills'])->name('v2.billing.list');
        Route::get('/bill/{id}', [App\Http\Controllers\V2\BillingController::class, 'billDetails'])->name('v2.billing.details');
        Route::post('/collect-due', [App\Http\Controllers\V2\BillingController::class, 'collectDue'])->name('v2.billing.collect_due');
        Route::post('/update-receipt', [App\Http\Controllers\V2\BillingController::class, 'updateReceipt'])->name('v2.billing.update_receipt');
        Route::get('/patient-draft-today/{patientId}', [App\Http\Controllers\V2\BillingController::class, 'patientDraftToday'])->name('v2.billing.patient_draft_today');
        Route::get('/refunds', [App\Http\Controllers\V2\BillingController::class, 'listRefunds'])->name('v2.billing.refunds');
        Route::get('/patient-refundable-bills/{patientId}', [App\Http\Controllers\V2\BillingController::class, 'patientRefundableBills'])->name('v2.billing.patient_refundable_bills');
    });

    Route::group(['prefix' => 'v2/visits'], function () {
        Route::get('/', [App\Http\Controllers\V2\OpdVisitController::class, 'index'])->name('v2.visits.index');
        Route::get('/list', [App\Http\Controllers\V2\OpdVisitController::class, 'listVisits'])->name('v2.visits.list');
    });

    // Message Center
    Route::group(['prefix' => 'diabetic-care'],function(){
        Route::get('/', [App\Http\Controllers\DiabeticController::class, 'index'])->name('diab.home');
    });

    // Message Center
    Route::group(['prefix' => 'message-center'],function(){
        Route::get('/', [App\Http\Controllers\MessageCenter::class, 'messageList'])->name('message.list');
        // Send Message
        Route::post('/send-bulk-message', [App\Http\Controllers\MessageCenter::class, 'sendBulkMessage'])->name('mc.send_bulk_message');
        Route::get('/get-template-body/{id}', [App\Http\Controllers\MessageCenter::class, 'getBody'])->name('sms.template.body');
        //Forms
        Route::get('/mc/fetch-visit-data', [App\Http\Controllers\MessageCenter::class, 'fetchVisitData'])->name('mc.fetch_visit_data');
        Route::get('/mc/all-patients', [App\Http\Controllers\MessageCenter::class, 'fetchAllPatientData'])->name('mc.all_patients');
        Route::post('/mc/visit-range', [App\Http\Controllers\MessageCenter::class, 'getVisitRangeData'])->name('mc.visit.range');
        Route::post('/mc/custom-filter', [App\Http\Controllers\MessageCenter::class, 'customPatientFilter'])->name('mc.custom.filter');
    });

    // Onboarding Hub — Meta Embedded Signup + WhatsApp per-clinic WABA
    Route::prefix('onboarding')->name('onboarding.')->group(function () {
        Route::get('/',                       [App\Http\Controllers\OnboardingController::class, 'index'])->name('dashboard');
        Route::get('/whatsapp',               [App\Http\Controllers\OnboardingController::class, 'whatsapp'])->name('whatsapp');
        Route::post('/whatsapp/onboard',      [App\Http\Controllers\OnboardingController::class, 'onboard'])->name('whatsapp.onboard');
        Route::delete('/whatsapp/disconnect', [App\Http\Controllers\OnboardingController::class, 'disconnectPhone'])->name('whatsapp.disconnect');
    });

    // Forms Routes
    Route::group(['prefix' => 'forms'], function () {
        Route::get('/', [App\Http\Controllers\HospitalController::class, 'hospital_forms'])->name('hospital.forms');
        Route::post('/store', [App\Http\Controllers\HospitalController::class, 'store_form_template'])->name('hospital.forms.store');
        Route::get('/print-template/{id}', [App\Http\Controllers\HospitalController::class, 'print_form_template'])->name('hospital.forms.print');
    });

    Route::group(['prefix' => 'mobile'], function () {
        Route::get('m-add-doctor',[App\Http\Controllers\mobile\MPatientController::class, 'add_doctor'])->name('m.add.doctor');
        Route::post('m-save-doctor',[App\Http\Controllers\mobile\MPatientController::class,'save_doctor'])->name('m.save.doctor');
        Route::get('m-edit-doctor',[App\Http\Controllers\mobile\MPatientController::class,'edit_doctor'])->name('m.edit.doctor');
        Route::post('m-update-doctor/{id}',[App\Http\Controllers\mobile\MPatientController::class,'update_doctor'])->name('m.update.doctor');
        Route::post('m-status-doctor',[App\Http\Controllers\mobile\MPatientController::class,'update_doctor_status'])->name('m.doctor.status');
        Route::get('m-ds-upload',[App\Http\Controllers\mobile\MPatientController::class,'patient_upload_ds'])->name('patient.ds.upload');
        Route::post('m-save-ds',[App\Http\Controllers\mobile\MPatientController::class,'save_patient_ds'])->name('save.patient.ds');
        Route::get('m-user-profile',[App\Http\Controllers\mobile\MPatientController::class,'profile'])->name('m.user.profile');

        Route::get('m-patient-diagnosis',[App\Http\Controllers\mobile\MPatientController::class,'m_patient_diagnosis'])->name('patient.diagnosis');
        Route::post('save-patient-diagnosis/{vid}',[App\Http\Controllers\mobile\MPatientController::class,'save_patient_diagnosis'])->name('save.patient.diagnosis');
        Route::post('update-patient-diagnosis/{vid}',[App\Http\Controllers\mobile\MPatientController::class,'update_patient_diagnosis'])->name('update.patient.diagnosis');

        Route::get('ipd-patient-visit-list',[App\Http\Controllers\mobile\MPatientController::class,'ipd_patient_visit_list'])->name('ipd.patient.patient_visit_list');
        Route::get('patient-ipd-admission',[App\Http\Controllers\mobile\MPatientController::class,'ipd_patient_admission'])->name('patient.ipd.admission');
        Route::post('save-ipd-admission/{id}',[App\Http\Controllers\mobile\MPatientController::class,'save_patient_admission'])->name('m.admit.patient');
        Route::get('ipd-patient-discharge', [App\Http\Controllers\mobile\MPatientController::class,'ipd_patient_discharge'])->name('patient.ipd.discharge');
        Route::get('ipd-patient-edit', [App\Http\Controllers\mobile\MPatientController::class,'ipd_patient_edit'])->name('patient.ipd.admission.edit');
        Route::put('ipd-patient-update/{id}',[App\Http\Controllers\mobile\MPatientController::class,'ipd_admission_update'])->name('m.admit.update');
    });



    Route::group(['prefix' => 'vaccine'], function () {
       Route::get('/patient/enrolled/{vid?}/{t_id?}',[App\Http\Controllers\VaccineController::class,'vaccination'])->name('patient.vaccination');
       Route::get('/enroll', [App\Http\Controllers\VaccineController::class,'enroll_patient'])->name('patient.enroll.vac');
       Route::post('/enroll-patient',[App\Http\Controllers\VaccineController::class,'enroll_patient_save'])->name('vaccination.enroll');
       Route::get('/patient/visitlist',[App\Http\Controllers\VaccineController::class,'fetch_patient_visit_list'])->name('patient.fetch_patient_visit_list');
       Route::get('/available-brands/{id}/{source}',[App\Http\Controllers\VaccineController::class,'vaccine_brands'])->name('vaccine.brands');
       Route::post('/save-as-given',[App\Http\Controllers\VaccineController::class,'vaccine_save_as_given'])->name('vaccine.save.given');
       Route::get('/edit-vaccine-schedule',[App\Http\Controllers\VaccineController::class,'edit_vaccine_schedule'])->name('edit.vaccine.schedule');
       Route::post('/update-vaccine-schedule/{id}',[App\Http\Controllers\VaccineController::class,'update_vaccine_schedule'])->name('update.vaccine.schedule');
       Route::post('patient/vaccine-print/{id}', [App\Http\Controllers\VaccineController::class,'vaccine_print'])->name('patient.vaccine.print');
       Route::get('add-new-vaccine',[App\Http\Controllers\VaccineController::class,'add_new_vaccine'])->name('add.new.vaccine');
       Route::post('save-new-vaccine',[App\Http\Controllers\VaccineController::class,'save_new_vaccine'])->name('save.new.vaccine');
       Route::post('update-vaccine-status',[App\Http\Controllers\VaccineController::class,'update_vaccine_status'])->name('update.vaccine.status');
       Route::get('vaccine-details',[App\Http\Controllers\VaccineController::class, 'vaccine_details'])->name('vaccine.details');

       Route::get('/vac-patient',[App\Http\Controllers\VaccineController::class, 'vac_patient'])->name('fetch.vac.patient');

       Route::get('insert-vac-log',[App\Http\Controllers\VaccineController::class, 'insert_vac_log'])->name('insert.vac_log');

       Route::post('insert-matching-record',[App\Http\Controllers\VaccineController::class, 'insert_matched_record'])->name('insert.matched_record');

       Route::get('add-new-bill',[App\Http\Controllers\VaccineController::class, 'add_new_bill'])->name('add.new.bill');
       Route::post('save-vaccine-bill',[App\Http\Controllers\VaccineController::class, 'save_vaccine_bill'])->name('save.vaccine.bill');

       Route::get('vac-testing',[App\Http\Controllers\VaccineController::class, 'test_patient_insert'])->name('vac.testing');

       Route::get('vaccine-charges',[App\Http\Controllers\VaccineController::class, 'vaccine_charges'])->name('vac.charges');

       Route::get('edit-brands-charges',[App\Http\Controllers\VaccineController::class, 'edit_brand_charges'])->name('edit.brands.charges');
       Route::post('update-brand-charges',[App\Http\Controllers\VaccineController::class, 'update_brand_charges'])->name('brands.update.charges');
       Route::get('vaccine-mark-group-brand',[App\Http\Controllers\VaccineController::class, 'vaccine_mark_group_brand'])->name('vaccine.mark_group.brands');

       Route::post('update-vaccine-mark-group-brand',[App\Http\Controllers\VaccineController::class, 'update_vaccine_mark_group_brand'])->name('update.vaccine.save.given.group');

       Route::post('update-followup',[App\Http\Controllers\VaccineController::class, 'update_followup'])->name('followup.update');

       Route::get('open-vaccine-bill/{id}',[App\Http\Controllers\VaccineController::class, 'edit_vaccine_bill'])->name('open_old_vaccine_bill');
    });


    Route::controller(IPDPatientController::class)->group(function(){
        Route::get('/ipd/view', 'ipd_view')->name('ipd.view');
        Route::get('/ipd/detail/{id}', 'ipd_detail')->name('ipd.detail');
        Route::get('/ipd/vitals-trend/{id}', 'vitals_trend')->name('ipd.vitals_trend');
        Route::get('ipd_patient/list','patient_list')->name('ipd_patient.list');
        Route::get('ipd_patient/billing','ipd_list_billing')->name('ipd.ipd_list_billing');
        Route::get('ipd_patient/viewbill/{id}','ipd_patient_viewbill')->name('ipd_patient.viewbill');

        // Route::middleware(['module-license:ipd_billing'])->group(function () {
        // });

        Route::get('ipd-discharge-checklist/{vid}/{ipd_no}','ipd_discharge_checklist')->name('ipd.discharge.checklist');
        Route::get('er-discharge-checklist/{vid}','er_discharge_checklist')->name('er.discharge.checklist');
        Route::post('ipd-checklist-update','checklist_status_update')->name('checklist.update.status');
        Route::post('ipd-checklist-update-checkall','checklist_status_update')->name('checklist.update.checkall');
        // Route::post('ipd-checklist-update','checklist_status_update')->name('checklist.update.status');
        // Route::post('ipd-checklist-checkall','checklist_status_update')->name('checklist.checkall');
        Route::post('ipd-discharge/initiate', 'initiate_discharge')->name('ipd.initiateDischarge');
        Route::post('er-discharge/initiate', 'er_initiate_discharge')->name('er.initiateDischarge');
        Route::post('ipd-clearance/toggle', 'toggle_clearance')->name('ipd.toggleClearance');
        Route::get('/consultation-request/{id}', 'consultation_request')->name('consultation.request');
        Route::get('change-doctor/{id}','change_doctor')->name('change.doctor');
        Route::post('ipd/change-doctor-save/{id}','save_doctor_request')->name('save.doctor.change');
        Route::post('update/doctor-change-status','update_doctor_id')->name('update.doctor.change.status');


        // Route::post('/merge-ipd-headwise-bill','merge_ipd_headwise_bill')->name('print.merge_ipd_headwise_bill');
          Route::post(
              '/multi-ipd-headwise-bill',
            'multi_ipd_headwise_bill2'
          )->name('print.multi_ipd_headwise_bill');

          Route::post(
              '/print/ipd-multiple-bill',
              'multi_ipd_headwise_bill'
          )->name('print.ipd_multiple_bill');

          Route::post(
              '/print/pharmacy-multiple-bill',
              'multi_pharmacy_bill'
          )->name('print.pharmacy_multiple_bill');


        Route::get('/ipd/{ipd_no}/print','printIpdDocuments')->name('ipd.print');

        Route::get('print-qr-labels','print_qr_labels')->name('print.qr.labels');
        Route::get('print-qr','print_label_sheet')->name('print.qr.label_sheet');

        Route::get('ipd/video-consent','getVideoConsent')->name('get.video.consent');
        Route::post('ipd/save-video-consent','saveVideoConsent')->name('save.video.consent');
        Route::get('ipd/upload-video-consent/{id}','uploadVideoConsent')->name('upload.video.consent');

        Route::post('upload/check',  'checkUploadedChunks')->name('patient.upload.check');
        Route::post('upload/chunk',  'uploadChunk')->name('patient.upload.chunk');
        Route::post('upload/final',  'finalizeUpload')->name('patient.upload.final');


        Route::get('ipd/patient-video-consent/{id}','getPatientVideoConsent')->name('patient.video_consent');
        Route::get('ipd/start-video-consent/{id}','startVideoConsent')->name('start.video_consent');
        Route::post('ipd/save-patient-video-consent','savePatientVideoConsent')->name('save.patient.video.consent');
        Route::post('ipd/update-video-consent/{id}','updateVideoConsent')->name('update.video.consent');
        Route::get('ipd/edit-video-consent/{id}','editVideoConsent')->name('admin.consents.edit');

        Route::get(
                      'ipd/get-ipd-visit/{ipd_id}',
                      'getIpdVisitId'
                  )->name('get.ipd.visit');

        Route::get('/load-investigation-blank', function () {
                    return view('components.clinical-forms.investigation', [
                        'investigation' => [],
                        'comments' => []
                    ])->render();
                })->name('load.new.investigation');

        Route::get('/load-investigation-blank-v2', function () {
                    return view('components.clinical-forms.investigation_v2', [
                        'investigation' => [],
                        'comments' => []
                    ])->render();
                })->name('load.new.investigation_v2');

        Route::get('ipd-clinical-patient/list','clinical_patient_list')->name('ipd_patient.clinical.list');
        Route::get('ipd_patient/dischargelist','patient_dis_list')->name('ipd_patient.dis_list');
        Route::get('er-patient/dischargelist','er_patient_dis_list')->name('er.discharge_list');
        Route::get('/ipd-patient-registration', 'patient_registration')->name('add.ipd_patient_registration');
        Route::get('/ipd-print-admission-form/{id}', 'print_admission_form')->name('ipd.print_admission_form');
        Route::get('/ipd-print-admission-form2/{id}', 'print_admission_form2')->name('ipd.print_admission_form2');
        Route::get('/emergecy-sheet-print', 'print_emergecy_sheet')->name('print_emergecy_sheet');
        Route::get('/ipd_get_room_types', 'getRoomTypes')->name('ipd_get_room_types');
        Route::get('/ipd_get_room_numbers',  'getRoomNumbers')->name('ipd_get_room_numbers');
        Route::post('ipd_patient/save','patient_save_ipd')->name('ipd_patient.save');
        Route::get('ipd_patient/visit/{id}','ipd_add_visit')->name('ipd_patient.visit');
        Route::post('ipd_patient_filter','ipd_patient_filter')->name('ipd_patient.filter');
        Route::post('ipd_dis_patient_filter','ipd_dis_patient_filter')->name('ipd_dis_patient.filter');
        Route::get('print_ipd_slip','print_ipd_slip')->name('print_ipd_slip');
        Route::get('print_ipd_label_sheet/{id}','print_ipd_label_sheet')->name('print_ipd_label_sheet');
        Route::get('ipd_patient/edit/{id}','edit_patient')->name('ipd.editPatient');
        Route::post('/update/{id}','update_patient')->name('ipd.updatePatient');
        Route::post('er-patient/admit/{id}','er_admit_patient')->name('er.admitPatient.ipd');

        Route::get('export-ipd-bill-list', 'exportIPDReportCsv')->name('export.ipd_discharge_report_list');
        Route::get('ipd/area_filter','area_filter')->name('area_filter');
        Route::post('ipd_area_filter','ipd_area_filter')->name('ipd.area_filter');
        Route::post('ipd_clear_area_filter','ipd_clear_area_filter')->name('ipd.clear_area_filter');
        Route::get('get/ward/floorwise','get_ward_floorwise')->name('get.ward.floorwise');
        Route::get('audit-log/{id}','activity_tracker')->name('activity_tracker');
        Route::get('patient-audit-log/{id}','patient_audit_log')->name('patient.audit_log');

        Route::get('fetch-nursing-charges','fetch_nursing_charges')->name('fetch_nursing_charges');
        Route::get('ipd_patient/add_payment_with_settlement','add_payment_with_settlement')->name('ipd_patient.add_payment_with_settlement');
        Route::post('admin/settlement/create_payment_with_settlement','create_payment_with_settlement')->name('admin.settlement.create_payment_with_settlement');
        Route::post('admin/settlement/unsettle_service_with_settlement','unsettle_service')->name('admin.settlement.unsettle_service_with_settlement');
        Route::get('ipd_patient/add_refund_of_excess','add_refund_of_excess')->name('ipd_patient.add_refund_of_excess');
        Route::post('admin/settlement/create_refund_of_excess','create_refund_of_excess')->name('ipd.create_refund_of_excess');

        Route::get('ipd_patient/add_payment_and_settlement','add_payment_and_settlement')->name('ipd_patient.add_payment_and_settlement');
        Route::post('admin/settlement/create_payment_and_settlement','create_payment_and_settlement')->name('ipd.create_payment_and_settlement');
        // ipd subbill -
        Route::get('ipd/patient-billing/{uhid?}/{req_id?}','ipd_subbill')->name('ipd.patient.billing');
        Route::post('patient/start-ipd_billing','start_ipd_billing')->name('patient.start_ipd_billing');
        Route::post('patient/gen-ipd_bill','gen_ipd_bill')->name('patient.gen_ipd_bill');
        Route::post('patient/start_ipd_patient','start_ipd_patient')->name('patient.start_ipd_patient');
        Route::get('sub-bill/history','sub_bill_history')->name('subbill.history');
        Route::get('show_subbill/{id}','subbill_show')->name('print.subbill');
        Route::get('ipd/billing/request','billing_request')->name('ipd.billing.request');
        Route::get('ipd/load-pending-request','load_pending_request')->name('ipd.load_pending_request');

        Route::get('pick-admission-consent-form','pick_admission_consent_form')->name('ipd.pick_admission_consent_form');
        Route::post('ipd_patient/update_admission_charge','update_admission_charge')->name('ipd_patient.update_admission_charge');

        Route::get('consent-forms','consent_forms')->name('ipd.consent_forms');
        Route::get('generate-consent-forms/{id}','generate_consent_forms')->name('ipd.generate_consent_form');
        Route::get('generate-estimage-cost-forms/{id}','generate_consent_forms')->name('ipd.generate_estimate_cost_form');
        #Route::get('generate-estimage-cost-forms/{id}','generate_estimate_cost_forms')->name('ipd.generate_estimate_cost_form');
        Route::get('generate-preview-consent-forms','generate_preview_consent_forms')->name('ipd.generate_preview_consent_form');

        Route::post('ipd/documents','ipdDocumentSave')->name('ipd.document.save');

        Route::get('admission-consent-form','admission_consent_form')->name('ipd.admission_consent_form');
        Route::get('print-admission-consent-form','print_admission_consent_form')->name('ipd.print_admission_consent_form');

        Route::get('admission-consent-form-eros','admission_consent_form_eros')->name('ipd.admission_consent_form.eros');
        Route::get('print-admission-consent-form-eros','print_admission_consent_form_eros')->name('ipd.print_admission_consent_form.eros');

        Route::get('cataract-surgery-consent-form','cataract_surgery_consent_form')->name('ipd.cataract_surgery_consent_form');
        Route::get('print-cataract-surgery-consent-form','print_cataract_surgery_consent_form')->name('ipd.print_cataract_surgery_consent_form');

        Route::get('ipd-admission-estimate-bill','admission_estimate_bill')->name('ipd.admission_estimate_bill');
        Route::get('ipd-admission-cost','admission_estimate_cost')->name('ipd.admission_estimate_cost');
        Route::get('print-admission-estimate-bill','print_admission_estimate_bill')->name('ipd.print_admission_estimate_bill');


        Route::get('ipd-admission-gate-pass','gatepass')->name('ipd.gatepass');
        Route::get('print-ipd-gate-pass/{id}','print_ipd_gatepass')->name('ipd.print_gatepass');

        Route::post('auto-entry-charges','auto_entry_charges')->name('auto_entry_charges');
        Route::get('ipd/auto-entry-charges-preview/{id}', 'get_auto_entry_charges_preview')->name('ipd.auto_entry_charges_preview');
        Route::post('ipd/apply-auto-entry-charges', 'apply_auto_entry_charges')->name('ipd.apply_auto_entry_charges');


        Route::post('save-context-template','save_context_template')->name('template.save_context_template');
        Route::get('get-context-template','get_context_template')->name('template.get_context_template');
        Route::match(['get', 'post'], 'delete-context-template','delete_context_template')->name('template.delete_context_template');

        Route::get('patient-document-management-form','patient_document_management_form')->name('ipd.patient_document_management_form');
        Route::post('patient-document-remove','remove_patient_document')->name('ipd.remove_patient_document');
        Route::post('patient-document-save','save_patient_document')->name('ipd.save_patient_document');
        Route::post('update-attachment-type','update_attachment_type')->name('update.attachment.type');
        Route::post('update-attachment-remarks','update_attachment_remarks')->name('update.attachment.remarks');

        Route::get('open-patient-document-management-form','open_patient_document_management_form')->name('ipd.open_patient_document_management_form');

        Route::get('fetch-lab-linked-investigations-table','fetch_linked_discharge_summary_investigations')->name('ipd_patient.fetch_linked_discharge_summary_investigations');


        Route::get('ipd-bill-copy-management-form','ipd_bill_copy_management_form')->name('ipd.ipd_bill_copy_management_form');
        Route::post('save-ipd-bill-copy','save_ipd_bill_copy')->name('ipd.save_ipd_bill_copy');
        // Route::post('patient-document-remove','remove_patient_document')->name('ipd.remove_patient_document');
        // Route::post('patient-document-save','save_patient_document')->name('ipd.save_patient_document');

        Route::get('show-service-changes-for-insurance-update','get_ipd_insurance_charges_for_update')->name('ipd.get_ipd_insurance_charges_for_update');
        Route::post('save-service-changes-for-insurance-update','save_ipd_insurance_charges_for_update')->name('ipd.save_ipd_insurance_charges_for_update');
        // Route::get('/get_hospital_insurance_charges_for_copy', [App\Http\Controllers\HospitalController::class, 'get_hospital_insurance_charges_for_copy'])->name('list.get_hospital_insurance_charges_for_copy');
        // Route::post('/save_hospital_insurance_charges_for_copy', [App\Http\Controllers\HospitalController::class, 'save_hospital_insurance_charges_for_copy'])->name('save.get_hospital_insurance_charges_for_copy');


        Route::post('ipd/save_comment','save_comment')->name('ipd.save_comment');
        Route::post('ipd/remove_comment','remove_comment')->name('ipd.remove_comment');

        Route::post('ipd/partially_settle_request','partially_settle_request')->name('ipd.partially_settle_request');
        Route::post('ipd/partially_settle_request_update','partially_settle_request_update')->name('ipd.partially_settle_request_update');
        Route::post('ipd/settle_ipd_bill','settle_ipd_bill')->name('ipd.settle_bill');

        Route::get('ipd_discharge_print_neonatal/{id}', 'ipd_discharge_print_neonatal')->name('ipd_discharge_print.neonatal');

        // Route::get()

        Route::get('ipd/dischargePatient', 'dischargePatient')->name('ipd.dischargePatient');
        Route::get('ipd/dischargePatient-from-er', 'dischargePatientFromEr')->name('er.dischargePatient');
        Route::get('ipd/admitPatient-from-er/{id}', 'admitPatientFromEr')->name('er.admitPatient');
        //Route::get('ipd/get/patient/details','ipd_getPatientDetails')->name('get.ipd.patient.details');
        Route::get('ipd/bed_transfer','bed_transfer')->name('ipd.bed_transfer');
        Route::get('ipd/get_bed_transfer','get_bed_transfer')->name('ipd.get_bed_transfer');
        Route::post('ipd/bed_transfer_update','bed_transfer_update')->name('bed_transfer.update');
        Route::get('ipd/bed_history/{id}','bed_history')->name('ipd.bed_history');
        Route::get('ipd/dashboard','ipd_dashboard')->name('ipd.dashboard');
        Route::post('ipd/hold_bed','hold_bed')->name('admin.ipd.hold_bed');
        Route::post('ipd/patient/transfer','patient_transfer')->name('admin.ipd.transfer');
        Route::post('ipd/transfer/approve','approve_bed_transfer')->name('ipd.transfer.approve');
        Route::post('ipd/transfer/reject','reject_bed_transfer')->name('ipd.transfer.reject');
        Route::get('ipd/pending-transfers','get_pending_transfers')->name('ipd.pending_transfers');

        Route::post('update/transfer/request','update_transfer_status')->name('bed.transfer.status');

        Route::get('all-ipd-bills','all_ipd_bill_list')->name('all_ipd_bill_list');

      // IPD admission request form
        Route::get('ipd_request_form','ipd_request_form')->name('ipd_request_form');

        Route::post('ipd_visit/save','ipd_visit_save')->name('ipd_visit.save');
        Route::get('ipd_getconsultcharges','ipd_get_consultcharges')->name('ipd_getconsultcharges');
        Route::get('ipd_visit/list','ipd_visit_list')->name('ipd_visit.list');
        Route::post('ipd_visit_filter','ipd_visit_filter')->name('ipd_visit.filter');
        Route::post('ipd_patient/startbill','ipd_patient_startbill')->name('ipd_patient.startbill');
        Route::get('ipd_get/patient/details','ipd_getPatientDetails')->name('ipd_get.patient.details');
        Route::get('/get_room_charges', 'getRoomCharges')->name('ipd_getRoomCharges');
        Route::get('/get_diagnostic_charges', 'getDiagnosticCharges')->name('ipd_getDiagnosticCharges');
        Route::get('/get_consumable_charges', 'getConsumableCharges')->name('ipd_getConsumableCharges');
        Route::post('ipd_brief_allowed','ipd_brief_allowed')->name('ipd.brief_allowed');

        Route::get('ipd/ot_admission','ot_admission')->name('ipd.ot_admission');
        Route::get('ipd/ot_admission_form','ot_admission_form')->name('ipd.ot_admission_form');
        Route::post('ipd_ot_admission/save/{id}','ot_admission_save')->name('ot_admission.save');
        Route::get('edit/ot-admission/{log_id}','edit_ot_admission')->name('edit.ot.admission');
        Route::get('ipd/ot-admission/edit-modal/{id}', 'edit_ot_admission_modal')
               ->name('edit.ot.admission_modal');

        Route::put('update/ot-admission/{log_id}','update_ot_admission')->name('ot_admission.update');

        Route::get('ipd/ot-admission/{vid}','ot_admission_form2')->name('ot.admission');

        // Ot delete route
        Route::post('ipd/ot_delete', 'deleteOtRecord')->name('delete_ot_record');

        Route::middleware(['module-license:ot'])->group(function () {

          // Separate OT Module Routes
          Route::get('ot/booking', 'ot_booking_index')->name('ot.booking');
          Route::get('ot/history', 'ot_history_index')->name('ot.history');
          Route::get('ot/setup', 'ot_setup_index')->name('ot.setup');
          Route::get('ot/print-history', 'print_ot_history')->name('ot.print_history');
          Route::get('ot/print-slip/{id}', 'print_ot_slip')->name('ot.print_slip');
          Route::post('ot/status-update/{id}', 'update_ot_status')->name('ot.status_update');
          Route::post('ot/cancel/{id}', 'cancel_ot_booking')->name('ot.cancel');
          Route::post('ot/undo-completion/{id}', 'undo_ot_completion')->name('ot.undo_completion');
          Route::get('ot/booking-form-ajax', 'get_ot_booking_form_ajax')->name('ot.booking_form_ajax');
          Route::get('ot/checklist-modal-ajax/{id}/{context}', 'get_ot_checklist_modal_ajax')->name('ot.checklist_modal_ajax');
          Route::post('ot/save-checklist-ajax/{id}', 'save_ot_checklist_ajax')->name('ot.save_checklist_ajax');
          Route::get('ot/items-used-modal-ajax/{id}', 'get_ot_items_used_modal_ajax')->name('ot.items_used_modal_ajax');
          Route::post('ot/save-items-used-ajax/{id}', 'save_ot_items_used_ajax')->name('ot.save_items_used_ajax');
          Route::post('ot/deduct-stock-consumption/{id}', 'deduct_ot_stock_consumption')->name('ot.deduct_stock_consumption');
          Route::post('ot/setup/room-save', 'save_ot_room_setup')->name('ot.setup.room_save');
          Route::post('ot/setup/checklist-save', 'save_ot_checklist_item_setup')->name('ot.setup.checklist_save');
          Route::post('ot/setup/checklist-toggle', 'toggle_ot_checklist_item')->name('ot.setup.checklist_toggle');

          // OT Surgery Notes Routes
          Route::get('ot/surgery-notes/{id}/{t_id?}', 'ot_surgery_notes')->name('ot.surgery_notes');
          Route::post('ot/surgery-notes/save/{id}', 'save_ot_surgery_notes')->name('ot.save_surgery_notes');
          Route::post('ot/surgery-notes/approve/{id}', 'approve_ot_surgery_notes')->name('ot.approve_surgery_notes');
          Route::get('ot/print-surgery-notes/{id}', 'print_ot_surgery_notes')->name('ot.print_surgery_notes');

          // OT Anesthesia Management Routes
          Route::get('ot/anesthesia', 'ot_anesthesia_index')->name('ot.anesthesia');
          Route::get('ot/anesthesia/pac/{id}', 'ot_anesthesia_pac')->name('ot.anesthesia.pac');
          Route::post('ot/anesthesia/pac/save/{id}', 'save_ot_anesthesia_pac')->name('ot.anesthesia.pac_save');
          Route::get('ot/anesthesia/note/{id}', 'ot_anesthesia_note')->name('ot.anesthesia.note');
          Route::get('ot/anesthesia/print/{id}', 'print_ot_anesthesia_note')->name('ot.anesthesia.print');
          Route::post('ot/anesthesia/note/save/{id}', 'save_ot_anesthesia_note')->name('ot.anesthesia.note_save');
          Route::get('ot/anesthesia/pacu/{id}', 'ot_anesthesia_pacu')->name('ot.anesthesia.pacu');
          Route::post('ot/anesthesia/pacu/save/{id}', 'save_ot_anesthesia_pacu')->name('ot.anesthesia.pacu_save');
          Route::post('ot/anesthesia/consent/save/{id}', 'save_ot_anesthesia_consent')->name('ot.anesthesia.consent_save');
          Route::post('ot/anesthesia/save-template/{id}', 'save_ot_anesthesia_template')->name('ot.anesthesia.save_template');

        });


        Route::get('ipd/ot-admission-bifurgation/{admission_id}','service_bifurgation')->name('bill.service_bifurgation');
        Route::post('save-service-bifurgation/{admission_id}','save_service_bifurgation')->name('save.service.bifurgation');
        Route::get('get-ot-admission-form','get_ot_admission_form')->name('get.ot_admission_form');
        Route::get('ipd/nursing-bifurgation/{admission_id}','nursing_bifurgation')->name('bill.nursing_bifurgation');

        Route::post('ipd/save_bill_amt','save_bill_amt')->name('ipd.save_bill_amt');
        Route::post('ipd/save-er-bill-amt','save_er_bill_amt')->name('er.save_bill_amt');
        Route::post('ipd/save_claim_id', 'save_claim_id')->name('ipd.saveClaimId');

        Route::post('ipd/settle-bill','ipd_settle_bill')->name('ipd_patient.settle_bill');

        Route::get('ipd-patient/service_order_request_form/{id}','service_order_request_form')->name('ipd_patient.service_order_request_form');
        Route::post('ipd-patient/service_order_request_save/{id}/{type?}','service_order_request_save')->name('ipd_patient.service_order_request_save');
        Route::post('ipd-patient/service_order_request_status_update/{id}/{type?}','service_order_request_status_update')->name('ipd_patient.service_order_request_status_update');
        Route::get('ipd-patient/print_service_order_request_form','print_service_order_indent')->name('ipd_patient.print_service_order_indent');

        Route::get('/ipd_patient_subbill/{id}','ipd_patient_subbill')->name('ipd_patient.subbill');
        Route::get('/ipd_get_aggregate_subbill_calculation/{id}','ipd_get_aggregate_subbill_calculation')->name('ipd_get_aggregate_subbill_calculation');



        Route::get('/get_doctors_list_ipd/{id}', 'getDoctorsList')->name('ipd_getDoctorsList');
        Route::get('/get_rooms_list_ipd/{id}', 'getRoomsList')->name('ipd_getRoomsList');
        Route::get('/get_aggregate_bill_calculation_ipd/{id}', 'get_aggregate_bill_calculation')->name('ipd_get_aggregate_bill_calculation');
        Route::get('/ipd_bill_getServicesList/{id}', 'getBillServicesList')->name('ipd_bill_getServicesList');
        Route::post('ipd_delete_service','ipd_delete_service')->name('ipd_delete_service');
        Route::post('apply_section_discounts','apply_section_discounts')->name('ipd_patient.apply_section_discounts');

        Route::get('/ipd_subbill_getDoctorsList/{id}', 'subbill_getDoctorsList')->name('ipd_subbill_getDoctorsList');
        Route::get('/ipd_subbill_getRoomsList/{id}', 'ipd_subbill_getRoomsList')->name('ipd_subbill_getRoomsList');
        Route::get('/ipd_subbill_getServicesList/{id}', 'ipd_subbill_getServicesList')->name('ipd_subbill_getServicesList');
        Route::get('/ipd_subbill_close/{id}', 'ipd_subbill_close')->name('ipd_subbill.close');
        // Route::get('/ipd_subbill_show', 'ipd_subbill_show')->name('ipd_subbill.show');

        Route::get('/get_package_charges', 'getPackageCharges')->name('ipd_getPackageCharges');
        Route::get('/get_pharmacy_charges', 'getPharmacyCharges')->name('ipd_getPharmacyCharges');
        Route::post('ipd_patient_admission/bill','ipd_patient_bill_admission')->name('ipd_patient.admission_charges');
        Route::post('ipd_patient_doctor/bill','ipd_patient_bill_doctor')->name('ipd_patient.bill_doctor');
        Route::post('ipd_patient_room/bill','ipd_patient_bill_room')->name('ipd_patient.bill_room');
        Route::post('ipd_patient_diagnostic/bill','ipd_patient_bill_diagnostic')->name('ipd_patient.bill_diagnostic');
        Route::post('ipd_patient_procedure/bill','ipd_patient_bill_diagnostic')->name('ipd_patient.bill_procedure');
        Route::post('ipd_patient_package/bill','ipd_patient_bill_package')->name('ipd_patient.bill_package');
        Route::post('ipd_patient_miscellaneous/bill','ipd_patient_bill_diagnostic')->name('ipd_patient.bill_miscellaneous');
        Route::get('/get_miscellaneous_charges', 'getMiscellaneousCharges')->name('ipd_getMiscellaneousCharges');
        Route::post('/ipd_update_status', 'updateStatus')->name('ipd_update_status');
        Route::post('/ipd_update_status2', 'updateStatus2')->name('ipd_update_status2');
        Route::get('ipd_patient/viewbill/pharmacy/{id}','ipd_patient_viewbill_pharmacy')->name('ipd_patient.viewbill_pharmacy');
        Route::get('/get_pharmacy_charges','getPharmacyCharges')->name('ipd_get_pharmacy_charges');
        Route::post('ipd_patient_pharmacy/bill','savePharmacies')->name('ipd_patient.save_pharmacies');
        Route::post('ipd_user_defined_bill/lock','ipd_user_defined_bill_lock')->name('ipd_user_defined_bill.lock');

        Route::post('ipd/isPartOfPackage','togglePackageCharge')->name('ipd.isPartOfPackage');

        Route::get('ipd/open_package_included_service','open_package_included_service')->name('ipd.open_package_included_service');

        Route::post('ipd/update_payment','update_payment')->name('ipd.update_payment');

        // Route::get('ipd/sevices_in_package','ipd_services_in_package')->name('ipd_services_in_package');

        Route::post('ipd_patient/remove_record','ipd_patient_remove_billdata')->name('ipd_patient.remove_record');
        Route::get('update/invoice_type', 'update_invoice_type')->name('update.invoice_type');

        Route::get('/getipdbilling','getipdbilling')->name('getipdbilling');
        Route::get('ipd_patient/discharge/{id}/{vid}','ipd_patient_discharge')->name('ipd_patient.discharge');
        Route::get('ipd_patient/discharge-now/{id}/{vid}','ipd_patient_discharge_now')->name('ipd_patient.discharge_now');

        Route::get('er_patient/discharge-now/{id}/{vid}','er_patient_discharge_now')->name('er_patient.discharge_now');

        Route::post('ipd_patient_discharge/save','saveipddischarge')->name('ipd_patient.save_discharge');
        Route::get('ipd_discharge_print/{id}', 'ipd_discharge_print')->name('ipd_discharge_print');
        Route::get('ipd_discharge_print_v2/{id}', 'ipd_discharge_print_v2')->name('ipd_discharge_print.v2');
        Route::get('ipd_discharge_print_v3/{id}', 'ipd_discharge_print_v3')->name('ipd_discharge_print.v3');
        Route::post('lock-ipd-patient', 'lockIPDPatient')->name('lockIPDPatient');
        Route::post('ipd_patient/save_investigation','save_investigation')->name('ipd_patient.save_investigation');
        Route::post('ipd_patient/save_investigation_2','save_investigation')->name('ipd_patient.save_investigation_v2');
        Route::post('ipd_patient/save_linked_investigation','save_linked_investigation')->name('ipd_patient.save_linked_investigation');
        Route::post('ipd_patient/copy_notes_to_discharge_summary','copy_notes_to_discharge_summary')->name('ipd.copy_notes_to_discharge_summary');
        Route::post('attachment/upload', 'attachment_upload')->name('attachment.upload');

        Route::get('update-bulk-qualification','syncDoctorQualificationsBulk');



        Route::post('ipd_patient/discharge-cancel','ipd_patient_cancel_discharge')->name('ipd_patient.discharge_cancel');

        Route::post('save_template', 'save_template')->name('save_template');
        Route::get('get_template/{id}', 'get_template')->name('get_template');
        Route::get('patient/load_old_comments', 'load_old_comments')->name('patient.load_old_comments');
        Route::post('patient/save_canvas_comments', 'save_canvas_comments')->name('patient.save_canvas_comments');
        Route::get('ipd_patient/show_investigation','show_investigation')->name('ipd_patient.show_investigation');
        Route::post('ipd_patient/update_investigation','update_investigation')->name('ipd_patient.update_investigation');

        Route::get('unique_types', 'getUniqueTypes')->name('unique_types');
        Route::get('unique_allergies', 'unique_allergies')->name('unique_allergies');
        Route::get('unique_past_medical_events', 'unique_past_medical_events')->name('unique_past_medical_events');
        Route::get('unique_family_history', 'unique_family_history')->name('unique_family_history');
        Route::get('unique_social_history', 'unique_social_history')->name('unique_social_history');
        Route::get('unique_diagnosis', 'unique_diagnosis')->name('unique_diagnosis');
        Route::get('unique_chiefcomplaint', 'unique_chiefcomplaint')->name('unique_chiefcomplaint');
        Route::get('unique_proceduredone', 'unique_proceduredone')->name('unique_proceduredone');
        Route::get('unique_hopi', 'unique_hopi')->name('unique_hopi');
        Route::get('unique_examnotes', 'unique_examnotes')->name('unique_examnotes');
        Route::get('unique_hospitalcourse', 'unique_hospitalcourse')->name('unique_hospitalcourse');
        Route::get('unique_condition', 'unique_condition')->name('unique_condition');
        Route::get('unique_advice', 'unique_advice')->name('unique_advice');
        Route::get('unique_commoninstr', 'unique_commoninstr')->name('unique_commoninstr');
        Route::get('unique_followup', 'unique_followup')->name('unique_followup');
        Route::get('unique_dietary', 'unique_dietary')->name('unique_dietary');
        Route::get('unique_phyactivity', 'unique_phyactivity')->name('unique_phyactivity');

        Route::get('ipd_medicine/search',  'ipd_medicinesearch')->name('ipd_medicine.search');

        Route::post('/ipd-medicine/store','store')->name('ipd_medicine.store');
        Route::get('/get-existing-medicines/{ipdId}', 'getExistingMedicines')->name('get_existing_medicines');

        Route::post('ipd/ipd_deletePayment','ipd_deletePayment')->name('ipd.deletePayment');

        Route::get('export-ipd-list', 'exportIPDCsv')->name('export.ipd_list');
        Route::get('export-ipd-bill-list', 'exportIPDReportCsv')->name('export.ipd_discharge_report_list');
        Route::get('ipd-patient-list-in-table', 'get_ipd_patient_in_list')->name('ipd_list.patient_in_list');

        Route::post('ipd/patient/save_user_defined_bill','save_user_defined_bill')->name('ipd_patient.save_user_defined_bill');
        Route::get('ipd/patient/print_user_defined_billing/{id}', 'print_user_defined_billing')->name('print_user_defined_billing');
        Route::get('ipd/headwise/print/{id}/{head}/{heading}', 'ipd_headwise_print')->name('print.ipd_headwise_bill');

        //Payment Adjustment
        Route::post('ipd_patient_save_paymentadjustment','ipd_patient_save_paymentadjustment')->name('ipd_patient.save_paymentadjustment');
        Route::post('ipd_patient_save_paymentRefund','ipd_patient_save_paymentRefund')->name('ipd_patient.save_paymentRefund');

        Route::post('save_ipd_payment_details','save_ipd_payment_details')->name('save.ipd_payment_details');
        Route::get('paymenthistory_receipt/{id}', 'paymenthistory_receipt')->name('paymenthistory_receipt');
        Route::get('ipd_patient_billing/{id}', 'ipd_patient_billing')->name('ipd_patient_billing');

        Route::get('emergency_patient_billing/{id}', 'emergency_patient_billing')->name('emergency_patient_billing');

        Route::get('get_ipd_patient_payments_slip/{id}', 'get_ipd_patient_payments_slip')->name('get_ipd_patient_payments_slip');
        Route::get('ipd_patient_billing_summary/{id}', 'ipd_patient_billing_summary')->name('ipd_patient.billing_summary');
        Route::post('edit_record','edit_record')->name('ipd_patient.edit_record');
        Route::post('ipd/make_charges_zero/{id}','make_charges_zero')->name('ipd.make_charges_zero');


        Route::middleware(['module-license:dietary'])->group(function () {

          Route::group(['prefix' => 'kitchen'], function () {
                Route::get('ipd/patient/food/order/{id}', 'food_order')->name('ipd_patient.food_order');
                Route::post('/save-food-order-template', 'save_food_order_template')->name('ipd_patient.save_food_order_template');
                Route::get('/remove-food-order-template', 'remove_food_order_template')->name('ipd_patient.remove_food_order_template');

                Route::get('add_item/kitchen', 'add_item_kitchen')->name('add_item.kitchen');
                Route::get('edit/kitchen/item/{id}', 'edit_kitchen_item')->name('edit.kitchen_item');
                Route::post('update/kitchen/item/{id}', 'update_kitchen_item')->name('update.item_kitchen');
                Route::post('update/kitchen/item/status', 'update_kitchen_item_status')->name('kitchen.item_status_update');
                Route::post('save/kitchen/item', 'save_kitchen_item')->name('save.item_kitchen');
                Route::post('save/food/order/request/{id}', 'save_food_order_request')->name('ipd_patient.food_order_request_save');
                Route::post('update/food/order/request/{id}', 'update_food_order_request')->name('ipd_patient.food_order_request_status_update');
                Route::get('/dashboard', 'kitchen_dashboard')->name('kitchen.dashboard');
                Route::post('/order/update/status', 'kitchen_order_update_status')->name('order.status.update');
                Route::get('edit/order/{ipd_no}/{id}', 'edit_order')->name('edit.order');
                Route::put('update/order/{ipd_no}/{id}', 'update_order')->name('ipd_patient.food_order_request_update');
          });

        });




        Route::get('order/requests','order_requests')->name('order.requests');
        Route::post('update/ipd/service/order','update_ipd_service_order')->name('ipd.service.order.update');
        Route::post('update/status/order/req','update_status_order_req')->name('ipd.update_status_order_req');

        // IPD Billing Header

        Route::get('ipd/billing/header', 'ipdBillingHeader')->name('ipd.billing_header');
        Route::post('ipd/billing/save', 'billingHeaderSave')->name('ipd.billing_header_save');
        Route::get('ipd/billing_head/edit','editHeadBill')->name('ipd.editHeadBill');
        Route::post('/ipd/toggle-active', 'billingHeadToggleActive')->name('ipd.billingHeadToggleActive');
        Route::get('ipd/billing_head//active-list', 'billngHeaderActiveList')->name('ipd.billngHeaderActiveList');
        /* Sortable table */
        Route::post('ipd/billing-header/update-sequence', 'updateSequence')->name('billing-header.update-sequence');

    });

    Route::controller(IPDPatientController::class)->group(function () {
        Route::get('ipd/checklist-setup/headers', 'getHeaders')->name('ipd.checklist_setup.headers');
        Route::post('ipd/checklist-setup/headers/save', 'saveHeader')->name('ipd.checklist_setup.headers.save');
        Route::delete('ipd/checklist-setup/headers/{id}', 'deleteHeader')->name('ipd.checklist_setup.headers.delete');
        Route::post('ipd/checklist-setup/headers/reorder', 'reorderHeaders')->name('ipd.checklist_setup.headers.reorder');

        Route::get('ipd/checklist-setup/items', 'getItems')->name('ipd.checklist_setup.items');
        Route::post('ipd/checklist-setup/items/save', 'saveItem')->name('ipd.checklist_setup.items.save');
        Route::delete('ipd/checklist-setup/items/{id}', 'deleteItem')->name('ipd.checklist_setup.items.delete');
        Route::post('ipd/checklist-setup/items/reorder', 'reorderItems')->name('ipd.checklist_setup.items.reorder');

        Route::get('ipd/patient-reviews/{visit_id}', 'checklistReviewsIndex')->name('ipd.patient_reviews');
        Route::get('ipd/patient-reviews/{visit_id}/create', 'checklistReviewsCreate')->name('ipd.patient_reviews.create');
        Route::post('ipd/patient-reviews/{visit_id}/save', 'checklistReviewsStore')->name('ipd.patient_reviews.save');
        Route::get('ipd/patient-reviews/show/{id}', 'checklistReviewsShow')->name('ipd.patient_reviews.show');
        Route::get('ipd/patient-reviews/edit/{id}', 'checklistReviewsEdit')->name('ipd.patient_reviews.edit');
        Route::post('ipd/patient-reviews/update/{id}', 'checklistReviewsUpdate')->name('ipd.patient_reviews.update');
        Route::get('ipd/patient-reviews/print/{id}', 'checklistReviewsPrint')->name('ipd.patient_reviews.print');
        Route::delete('ipd/patient-reviews/delete/{id}', 'checklistReviewsDestroy')->name('ipd.patient_reviews.delete');
    });

    Route::group(['prefix' => 'radiology'], function () {
        Route::get('orders',[App\Http\Controllers\admin\PatientController::class, 'radiology_orders'])->name('radiology.orders');
        Route::get('orders/outsource',[App\Http\Controllers\admin\PatientController::class, 'radiology_orders_outsource'])->name('radiology.orders.outsource');
        Route::get('report-upload/{vid?}/{t_id?}',[App\Http\Controllers\admin\PatientController::class, 'radiology_report_upload'])->name('radiology.radiology_report_upload');
        Route::get('report-upload-outsource/{vid?}/{t_id?}',[App\Http\Controllers\admin\PatientController::class, 'radiology_report_upload_outsource'])->name('radiology.radiology_report_upload_outsource');
        Route::post('report-save',[App\Http\Controllers\admin\PatientController::class, 'save_procedure_report_data'])->name('save_procedure_report_data');
        Route::get('print-result/{id?}',[App\Http\Controllers\admin\PatientController::class, 'procedure_report_print'])->name('radiology.procedure_report_print');

        Route::get('get-radiology-attachment-form',[App\Http\Controllers\admin\PatientController::class, 'radiology_attachment_form'])->name('radiology.attachment_form');
        Route::post('save-radiology-attachment-form',[App\Http\Controllers\admin\PatientController::class, 'save_radiology_attachment'])->name('radiology.save_radiology_attachment');
        Route::post('remove-radiology-attachment',[App\Http\Controllers\admin\PatientController::class, 'remove_radiology_attachment'])->name('radiology.remove_radiology_attachment');
        Route::get('view-radiology-attachment-modal',[App\Http\Controllers\admin\PatientController::class, 'view_radiology_attachment_modal'])->name('radiology.view_attachment_modal');
        Route::post('approve-investigation',[App\Http\Controllers\admin\PatientController::class, 'approve_invenstigation'])->name('approve_invenstigation');

        Route::post('send-whatsapp-radiology-attachment',[App\Http\Controllers\admin\PatientController::class, 'send_whatsapp_radiology_attachment'])->name('radiology.send_whatsappp');

        Route::post('template/mark-default',[App\Http\Controllers\admin\PatientController::class, 'markTemplateDefault'])->name('template.mark_default');
        Route::post('follow-up-date/update',[App\Http\Controllers\admin\PatientController::class, 'updateFollowUpDate'])->name('radiology.follow-up-date.update');
    });

    Route::group(['prefix' => 'procedures'], function () {
        Route::get('orders/{procedure_type}',[App\Http\Controllers\admin\PatientController::class, 'procedure_orders'])->name('procedures.orders');
        Route::get('report-upload/{vid?}/{t_id?}',[App\Http\Controllers\admin\PatientController::class, 'radiology_report_upload'])->name('procedures.report_upload');
        Route::post('report-save',[App\Http\Controllers\admin\PatientController::class, 'save_procedure_report_data'])->name('procedures.save_procedure_report_data');
        Route::get('print-result/{id?}',[App\Http\Controllers\admin\PatientController::class, 'procedure_report_print'])->name('procedure.procedure_report_print');

        Route::post('approve-investigation',[App\Http\Controllers\admin\PatientController::class, 'approve_invenstigation'])->name('procedures.approve_invenstigation');
    });

    Route::middleware(['module-license:human_resource'])->group(function () {

      Route::group(['prefix' => 'hr'], function () {
        Route::get('emp/registration',[App\Http\Controllers\HrController::class, 'register'])->name('hr.dashboard');
        Route::post('registration/save', [App\Http\Controllers\HrController::class, 'save_registration'])->name('hr.registration.save');
        Route::get('edit/{id}', [App\Http\Controllers\HrController::class, 'edit'])->name('hr.emp.edit');
        Route::put('register/{id}', [App\Http\Controllers\HrController::class, 'update'])->name('hr.registration.update');
        Route::get('emp/list', [App\Http\Controllers\HrController::class, 'list'])->name('hr.emp.list');
        Route::get('designation/list', [App\Http\Controllers\HrController::class, 'designation_list'])->name('hr.designation.list');
        Route::post('designation/create', [App\Http\Controllers\HrController::class, 'deignation_create'])->name('designation.create');
        Route::get('/designation/edit/{id}', [App\Http\Controllers\HrController::class, 'edit_designation'])->name('designation.edit');
        Route::put('/designation/update/{id}', [App\Http\Controllers\HrController::class, 'designation_update'])->name('designation.update');
        Route::post('/designation/status', [App\Http\Controllers\HrController::class, 'designation_status'])->name('designation.status');
        Route::get('department/list', [App\Http\Controllers\HrController::class, 'department_list'])->name('department.list');
        Route::post('department/create', [App\Http\Controllers\HrController::class, 'department_create'])->name('department.create');
        Route::get('/department/edit/{id}', [App\Http\Controllers\HrController::class, 'edit_department'])->name('department.edit');
        Route::put('/department/update/{id}', [App\Http\Controllers\HrController::class, 'department_update'])->name('department.update');
        Route::post('/department/status', [App\Http\Controllers\HrController::class, 'department_status'])->name('department.status');
        Route::get('leave/apply', [App\Http\Controllers\HrController::class, 'leave_apply'])->name('leave.apply');
        Route::post('save/leave/apply', [App\Http\Controllers\HrController::class, 'save_leave_apply'])->name('leave.apply.save');
        Route::post('update/leave/apply/{id}', [App\Http\Controllers\HrController::class, 'update_leave_apply'])->name('leave.apply.update');
        Route::get('leave/apply/edit/{id}', [App\Http\Controllers\HrController::class, 'edit_leave_apply'])->name('leave.apply.edit');
        Route::post('leave/apply/status', [App\Http\Controllers\HrController::class, 'leave_apply_status'])->name('leave.apply.status');
        Route::get('leave/list', [App\Http\Controllers\HrController::class, 'leave_list'])->name('leave.list');
        Route::get('setup', [App\Http\Controllers\HrController::class, 'hr_setup'])->name('hr.setup');
        Route::post('update/setup', [App\Http\Controllers\HrController::class, 'update_setup'])->name('hr.setup.update');
        Route::get('holidays-master',[App\Http\Controllers\HrController::class, 'holidays_master'])->name('holiday.master');
        Route::post('create/holidays-master',[App\Http\Controllers\HrController::class, 'create_holidays_master'])->name('create.holiday.master');
        Route::get('edit/holidays-master/{id}',[App\Http\Controllers\HrController::class, 'edit_holidays_master'])->name('edit.holiday.master');
        Route::post('update/holidays-master/{id}',[App\Http\Controllers\HrController::class, 'update_holidays_master'])->name('update.holiday.master');
        Route::get('delete/holidays-master/{id}',[App\Http\Controllers\HrController::class, 'delete_holidays_master'])->name('delete.holiday.master');
        Route::get('update/holidays-status',[App\Http\Controllers\HrController::class, 'update_holiday_status'])->name('holiday.status.update');
        Route::get('setup-edit/{id}', [App\Http\Controllers\HrController::class, 'setup_edit'])->name('setup.edit');
        Route::post('update/weekoff', [App\Http\Controllers\HrController::class, 'update_weekoff'])->name('hr.weekoff.update');
        Route::get('attandence-reconcilation/{id}',[App\Http\Controllers\HrController::class,'attendace_reconciliation'])->name('attandance.reconciliation');
        Route::get('attendance-log/{id}',[App\Http\Controllers\HrController::class,'attendance_log'])->name('attendance.log');
        Route::post('attendance/update/pair', [App\Http\Controllers\HrController::class, 'update_attendance_pair'])->name('attendance.update.pair');
        Route::get('generate-pair', [App\Http\Controllers\HrController::class, 'generate_pair'])->name('generate.pair');
        Route::get('change/pair-status',[App\Http\Controllers\HrController::class, 'change_pair_status'])->name('change.pair_status');
        Route::get('change/attendance/status',[App\Http\Controllers\HrController::class, 'change_attendance_status'])->name('change.attendance.status');
        Route::get('make-payable',[App\Http\Controllers\HrController::class, 'make_payable'])->name('change.attendance.make_payable');
        Route::post('update/attendance/payable', [App\Http\Controllers\HrController::class, 'update_attendance_payable'])->name('attendance.update.payable');
        Route::post('change/attendance/reject',[App\Http\Controllers\HrController::class, 'change_attendance_reject'])->name('change.attendance.reject');
        Route::get('get-paired-attendance',[App\Http\Controllers\HrController::class, 'getPairedAttendance'])->name('get.paired.attendance');

        Route::get('edit-department-role', [App\Http\Controllers\HrController::class, 'edit_department_role'])->name('edit_department_role');
        Route::post('employee/update-role', [App\Http\Controllers\HrController::class, 'update_employee_role'])->name('emp.update_employee_role');

        Route::get('change/weekly-off', [App\Http\Controllers\HrController::class, 'change_weekly_off'])->name('change.weekly_off');
        //genrate payroll
        Route::get('payroll', [App\Http\Controllers\HrController::class, 'payroll'])->name('hr.payroll');
        Route::get('generate/payroll', [App\Http\Controllers\HrController::class, 'generate_payroll'])->name('hr.payroll.generate');
        Route::get('pay-slip/{id}', [App\Http\Controllers\HrController::class, 'pay_slip'])->name('print.pay.slip');
        Route::post('unlock/salary', [App\Http\Controllers\HrController::class, 'unlock_salary'])->name('hr.unlock.salary');

        Route::get('attendances',[App\Http\Controllers\HrController::class, 'attandance'])->name('hr.attandance');

        Route::get('view/{id}',[App\Http\Controllers\HrController::class, 'view'])->name('hr.staff.view');
        Route::post('attendance/override', [App\Http\Controllers\HrController::class, 'attendance_override'])->name('hr.attendance.override');

        Route::get('attendance/userwise/{id}', [App\Http\Controllers\HrController::class, 'attendance_userwise'])->name('hr.attendance.userwise');
        Route::get('attendance/userwise2/{id}', [App\Http\Controllers\HrController::class, 'attendance_userwise2'])->name('hr.attendance.userwise2');

        Route::get('staff/export/{type}', [App\Http\Controllers\HrController::class, 'exportStaffCsv'])->name('hr.staff.export');
        Route::post('attendance/create', [App\Http\Controllers\HrController::class, 'attendance_create'])->name('hr.attendance.create');
        Route::get('generate/salary/{id}/{month}/{year}', [App\Http\Controllers\HrController::class, 'generate_salary'])->name('gen.salary');
        Route::post('users/import', [App\Http\Controllers\HrController::class, 'import'])->name('users.import');

        Route::get('/users-bulk-upload/review-table/{table}', [App\Http\Controllers\HrController::class, 'review_bulk_imported_items'])->name('hr.review_bulk_imported_items');

        Route::post('save/employee/salary',[App\Http\Controllers\HrController::class, 'save_employee_salary'])->name('save.emp.salary');

        Route::get('salary-settings', [App\Http\Controllers\HrController::class, 'setup_settings'])->name('setup.settings');
        Route::post('salary-settings',[App\Http\Controllers\HrController::class, 'save_salary_settings'])->name('hr.salary.setting');

        Route::get('upcomming/birthday', [App\Http\Controllers\HrController::class,'upcomming_birthday'])->name('upcomming.birthday.list');

        // Joining Letter
        Route::get('joining-letter-print/{id}', [App\Http\Controllers\HrController::class,'print_letter'])->name('print.letter');

        // Joining Letter
        Route::get('joining-letter/{id}', [App\Http\Controllers\HrController::class,'joining_letter'])->name('joining.letter');

        // Code of conduct
        Route::get('code-conduct/{id}', [App\Http\Controllers\HrController::class,'code_of_conduct'])->name('code.conduct');

        // Salary information
        Route::get('salary-information/{id}', [App\Http\Controllers\HrController::class,'salary_information'])->name('salary.info');
        Route::post('save/employee/salary_info',[App\Http\Controllers\HrController::class,'save_salary_information'])->name('save.salary.info');
        Route::get('emp/salary/edit/{id}',[App\Http\Controllers\HrController::class,'empSalaryInfoEdit'])->name('emp.salary.edit');
        Route::put('emp/salary/update/{id}', [App\Http\Controllers\HrController::class,'updateEmpSalaryInfo'])->name('update.emp.salary.info');
        // Salary Revised
        Route::get("emp/salary/revised_list",[App\Http\Controllers\HrController::class,'salaryReviseList'])->name('hr.salary.revised.list');
        Route::get("emp/salary/revised_view/{id}",[App\Http\Controllers\HrController::class,'salaryReviseView'])->name('hr.salary.revised.view');
        Route::post('emp/salary/status-update', [App\Http\Controllers\HrController::class, 'updateStatus'])->name('salary.approved.status');
        Route::get('salary/revised_edit/{id}',[App\Http\Controllers\HrController::class,'salary_revised_edit'])->name('salary.revised.edit');
        Route::put('emp/salary/revised_update/{id}',[App\Http\Controllers\HrController::class,'salary_revised_update'])->name('salary.revised.update');

        // Appointment Letter Information
        Route::get('emp/appointment/letter/info/{id}',[App\Http\Controllers\HrController::class,'appointmentLetterInfo'])->name('appointment.ltr.info');

        // PDF Generated
        Route::get('/hr/appointment-letter/download/{id}', [App\Http\Controllers\HrController::class, 'downloadAppointmentLetter'])->name('appointment.download');

        Route::post('save/joining-letter',[App\Http\Controllers\HrController::class,'save_joining_letter'])->name('setup.joining.letter');
        Route::get('fetch-joining-letter',[App\Http\Controllers\HrController::class,'fetch_joining_letter'])->name('fetch.joining.letter');
        Route::get('fetch-appointment-letter',[App\Http\Controllers\HrController::class,'fetch_appointment_letter'])->name('fetch.appointment.letter');

        Route::get('emp-profile-print/{id}',[App\Http\Controllers\HrController::class,'emp_profile_print'])->name('emp.profile.print');
        // Route::get('update-currrent_salary', [\App\Http\Controllers\HrController::class, 'update_current_salary_of_all_users'])->name('update.current_salary');
        Route::get('print-leave-report/{id}',[App\Http\Controllers\HrController::class,'print_leave_report'])->name('print.leave.report');
      });
    });




    Route::get('/dashboard', [App\Http\Controllers\HospitalController::class, 'index'])->name('dashboard');
    Route::get('/blank-page', [App\Http\Controllers\HospitalController::class, 'index_blank'])->name('blank_index');
    Route::get('/hospital-login', [App\Http\Controllers\HospitalController::class, 'index'])->name('hospital.home');
    Route::post('/get-collection-summary', [App\Http\Controllers\HospitalController::class, 'get_collection_summary'])->name('get_collection_summary');
    Route::get('/opd-report', [App\Http\Controllers\HospitalController::class, 'opd_report'])->name('report.opd');
    Route::get('/profile', [App\Http\Controllers\HospitalController::class, 'profile'])->name('profile');

    Route::get('/fetch-counts', [App\Http\Controllers\HospitalController::class, 'fetchCounts'])->name('fetch.counts');
    Route::get('/opd-fetch-counts', [App\Http\Controllers\HospitalController::class, 'opdfetchCounts'])->name('opd.fetch.counts');
    Route::get('/opd/count', [App\Http\Controllers\HospitalController::class, 'getOPDCount'])->name('opd.count');
    Route::get('/opd/doctor/count', [App\Http\Controllers\HospitalController::class, 'getOPDDoctorCount'])->name('opd.doctor.count');

    Route::get('/opd/get-detailed-report', [App\Http\Controllers\HospitalController::class, 'getOPDDetailedReport'])->name('opd.detailed_report');
    Route::get('/opd/get-revenue-detailed-report', [App\Http\Controllers\HospitalController::class, 'getOPDRevenueDetailedReport'])->name('opd.revenue_detailed_report');

    //ipd tab
    Route::get('/ipd-fetch-counts', [App\Http\Controllers\HospitalController::class, 'ipdfetchCounts'])->name('ipd.fetch.counts');
    Route::get('/ipd/count', [App\Http\Controllers\HospitalController::class, 'getIPDCount'])->name('ipd.count');
    Route::get('/ipd/totalcount', [App\Http\Controllers\HospitalController::class, 'getIPDtotalCount'])->name('ipd.totalcount');
    // Route::get('/ipd/doctor/count', [App\Http\Controllers\HospitalController::class, 'getIPDDoctorCount'])->name('ipd.doctor.count');
    Route::get('/ipd/get-patient-registered-report', [App\Http\Controllers\HospitalController::class, 'getPatientRegisteredReport'])->name('opd.get_patient_registered_report');
    Route::get('/ipd/get-new-admission-report', [App\Http\Controllers\HospitalController::class, 'getDischargedReport'])->name('ipd.discharged_report');
    // Route::get('/ipd/get-discharges-report', [App\Http\Controllers\HospitalController::class, 'getDischargedReport'])->name('ipd.discharged_report');
    Route::get('/ipd/get-revenue-report', [App\Http\Controllers\HospitalController::class, 'getIPDRevenueReport'])->name('ipd.revenue_detailed_report');
  //  Route::get('/ipd/ward/count', [App\Http\Controllers\HospitalController::class, 'getWardCount'])->name('ipd.ward.count');

    Route::get('other_service_charges', [App\Http\Controllers\HospitalController::class, 'fetchOtherServices'])->name('fetch_other_service');
    Route::post('update/charges/bifurcation', [App\Http\Controllers\HospitalController::class, 'updateChargesBifurcation'])->name('update.charges_bifurcation');

    Route::get('/download-patient-details', [App\Http\Controllers\HospitalController::class, 'downloadPatientDetails'])->name('download.patient.details');
    Route::get('/download-visit-details', [App\Http\Controllers\HospitalController::class, 'downloadVisitDetails'])->name('download.visit.details');
    Route::get('/download-ipdaddm-details', [App\Http\Controllers\HospitalController::class, 'downloadIPDAddmDetails'])->name('download.ipdaddm.details');
    Route::get('/download-ipddisch-details', [App\Http\Controllers\HospitalController::class, 'downloadIPDDischDetails'])->name('download.ipddisch.details');
    Route::get('/download-opd-visit-details', [App\Http\Controllers\HospitalController::class, 'downloadopdVisitDetails'])->name('download.opd.visit.details');
    Route::get('/download-ipd-visit-details', [App\Http\Controllers\HospitalController::class, 'downloadipdVisitDetails'])->name('download.ipd.visit.details');
    Route::get('/download-ipd-status-details', [App\Http\Controllers\HospitalController::class, 'downloadipdStatusDetails'])->name('download.ipd.status.details');
    Route::get('/download-ipd-dis-visit-details', [App\Http\Controllers\HospitalController::class, 'downloadipddisVisitDetails'])->name('download.ipddis.visit.details');
    Route::get('/opddoctors_by_specialization/{specialization_id}', [App\Http\Controllers\HospitalController::class, 'getopdDoctors'])->name('get.opddoctor.spec');

    Route::get('/hospital_add_doctor', [App\Http\Controllers\HospitalController::class, 'hospital_add_doctor'])->name('add.hospital_doctor');
    Route::post('/save_hospital_doctor', [App\Http\Controllers\HospitalController::class, 'save_hospital_doctor'])->name('save.hospital_doctor');
    Route::get('/list_hospital_doctor', [App\Http\Controllers\HospitalController::class, 'list_hospital_doctor'])->name('list.hospital_doctor');
    Route::post('/update_hospital_doctor', [App\Http\Controllers\HospitalController::class, 'update_hospital_doctor'])->name('update.hospital_doctor');
    Route::post('/doctor/update-order', [App\Http\Controllers\HospitalController::class, 'update_doctor_order'])->name('hospital_doctor.update_order');
    Route::post('/create_login_cred', [App\Http\Controllers\HospitalController::class, 'create_login_cred'])->name('doctor.create_login_cred');
    Route::post('hospital_doctor_settings',[App\Http\Controllers\HospitalController::class, 'doctor_settings'])->name('hospital_doctor.settings');
    Route::post('update_doctor_settings',[App\Http\Controllers\HospitalController::class, 'update_doctor_settings'])->name('update_doctor.settings');
    Route::post('/update_doctor_onboarding', [App\Http\Controllers\HospitalController::class, 'update_doctor_onboarding'])->name('hospital_doctor.update_onboarding');

    Route::get('referral_doctor_list', [App\Http\Controllers\HospitalController::class, 'referral_doctor_list'])->name('referral_doctor.list');
    Route::post('/referral-users/update', [App\Http\Controllers\HospitalController::class,'updateReferralUser'])->name('referral-users.update');


    Route::get('/hospital_add_insurance', [App\Http\Controllers\HospitalController::class, 'hospital_add_insurance'])->name('add.hospital_insurance');
    Route::post('/save_hospital_insurance', [App\Http\Controllers\HospitalController::class, 'save_hospital_insurance'])->name('save.hospital_insurance');
    Route::get('/list_hospital_insurance', [App\Http\Controllers\HospitalController::class, 'list_hospital_insurance'])->name('list.hospital_insurance');

    // Export functionality
    Route::post('/insurance/chargers',[App\Http\Controllers\HospitalController::class, 'insuranceCharges'])->name('insurance_charges');
    Route::post('/export-insurance', [App\Http\Controllers\HospitalController::class, 'exportInsurance'])->name('export.insurance');

    Route::get('/get_hospital_insurance_charges', [App\Http\Controllers\HospitalController::class, 'get_hospital_insurance_charges'])->name('list.get_hospital_insurance_charges');
    Route::get('/get_hospital_insurance_charges_for_copy', [App\Http\Controllers\HospitalController::class, 'get_hospital_insurance_charges_for_copy'])->name('list.get_hospital_insurance_charges_for_copy');
    Route::post('/save_hospital_insurance_charges_for_copy', [App\Http\Controllers\HospitalController::class, 'save_hospital_insurance_charges_for_copy'])->name('save.get_hospital_insurance_charges_for_copy');
    Route::get('/hospital_insurance_charges_list', [App\Http\Controllers\HospitalController::class, 'hospital_insurance_charges_list'])->name('list.hospital_insurance_charges_list');

    Route::get('emergency-charges',[App\Http\Controllers\HospitalController::class, 'emergency_charges'])->name('emergency.charges');

    Route::get('/list_hospital_tpa', [App\Http\Controllers\HospitalController::class, 'list_hospital_tpa'])->name('list.list_hospital_tpa');

    Route::get('/hospital_add_service', [App\Http\Controllers\HospitalController::class, 'hospital_add_service'])->name('add.hospital_service');
    Route::post('/save_hospital_service', [App\Http\Controllers\HospitalController::class, 'save_hospital_service'])->name('save.hospital_service');
    Route::get('/list_hospital_service', [App\Http\Controllers\HospitalController::class, 'list_hospital_service'])->name('list.hospital_service');
    Route::post('/update_hospital_service', [App\Http\Controllers\HospitalController::class, 'update_hospital_service'])->name('list.update_hospital_service');
    Route::get('/get_hospital_service_charges', [App\Http\Controllers\HospitalController::class, 'get_hospital_service_charges'])->name('list.get_hospital_service_charges');
    Route::post('/update_hospital_service_charges', [App\Http\Controllers\HospitalController::class, 'update_hospital_service_charges'])->name('list.update_hospital_service_charges');
    Route::get('/update_hospital_service_cost', [App\Http\Controllers\HospitalController::class, 'update_hospital_service_cost'])->name('list.update_hospital_service_cost');
    Route::post('/update_hospital_service_charges_all', [App\Http\Controllers\HospitalController::class, 'update_hospital_service_charges_all'])->name('list.update_hospital_service_charges_all');

    // Common Charges Update Routes
    Route::get('/view-charges', [App\Http\Controllers\HospitalController::class, 'hospital_view_charges'])->name('view.hospital_view_charges');
    Route::get('/update-charges', [App\Http\Controllers\HospitalController::class, 'hospital_update_charges'])->name('update.hospital_view_charges');

    // Hospital Lab Packages Routes
    Route::get('/lab_packages', [App\Http\Controllers\HospitalController::class, 'lab_packages'])->name('lab_packages.list');
    Route::post('/add_lab_papckage', [App\Http\Controllers\HospitalController::class, 'add_lab_papckage'])->name('add.lab_package');
    Route::post('/update_lab_papckage', [App\Http\Controllers\HospitalController::class, 'update_lab_papckage'])->name('update.lab_package');
    Route::get('/change_lab_package_status',[App\Http\Controllers\HospitalController::class,'change_lab_package_status'])->name('status_update.lab_package');


    Route::get('/hospital_add_servicecharges', [App\Http\Controllers\HospitalController::class, 'hospital_add_servicecharges'])->name('add.hospital_servicecharges');
    Route::post('/save_hospital_servicecharges', [App\Http\Controllers\HospitalController::class, 'save_hospital_servicecharges'])->name('save.hospital_servicecharges');
    Route::get('/list_hospital_servicecharges', [App\Http\Controllers\HospitalController::class, 'list_hospital_servicecharges'])->name('list.hospital_servicecharges');
    Route::post('/update_hospital_servicecharges', [App\Http\Controllers\HospitalController::class, 'update_hospital_servicecharges'])->name('update.hospital_servicecharges');
    Route::get('getOptions/{id}', [App\Http\Controllers\HospitalController::class, 'get_insurance'])->name('getOptions');

    Route::get('/hospital_add_registrationfee', [App\Http\Controllers\HospitalController::class, 'hospital_add_registrationfee'])->name('add.hospital_registrationfee');
    Route::get('/hospital_add_admissionfee', [App\Http\Controllers\HospitalController::class, 'hospital_add_admissionfee'])->name('add.hospital_admissionfee');

    Route::get('/hospital_charges', [App\Http\Controllers\HospitalController::class, 'hospital_charges'])->name('add.hospital_charges');
    Route::post('/save_hospital_registrationfee', [App\Http\Controllers\HospitalController::class, 'save_hospital_registrationfee'])->name('save.hospital_registrationfee');
    Route::post('/save_hospital_admissionfee', [App\Http\Controllers\HospitalController::class, 'save_hospital_admissionfee'])->name('save.hospital_admissionfee');



    Route::get('/doctorchangeStatus',[App\Http\Controllers\HospitalController::class,'doctor_status_update'])->name('doctor.status._update');
    Route::get('/insurancechangeStatus',[App\Http\Controllers\HospitalController::class,'insurance_status_update'])->name('insurance.status_update');
    Route::get('/servicechangeStatus',[App\Http\Controllers\HospitalController::class,'service_status_update'])->name('service.status_update');
    Route::get('/serviceOutsourceChangeStatus',[App\Http\Controllers\HospitalController::class,'service_outsource_status_update'])->name('service.service_outsource_status_update');
    Route::get('/servicechangeOnboardStatus',[App\Http\Controllers\HospitalController::class,'service_status_onboard'])->name('service.service_status_onboard');
    Route::get('/hospital_add_consultcharge', [App\Http\Controllers\HospitalController::class, 'hospital_add_consultcharge'])->name('add.hospital_consultcharge');
    Route::post('/save_hospital_consultcharge', [App\Http\Controllers\HospitalController::class, 'save_hospital_consultcharge'])->name('save.hospital_consultcharge');
    Route::get('/consultcharge_status', [App\Http\Controllers\HospitalController::class, 'consultcharge_status'])->name('update.hospital_consultcharge_status');
    Route::get('/consultcharge_status2', [App\Http\Controllers\HospitalController::class, 'consultcharge_status2'])->name('update.hospital_consultcharge_status2');
    Route::get('fetch-hospital-ward', [App\Http\Controllers\HospitalController::class, 'fetch_hospital_ward'])->name('fetch.hospital.ward');
    Route::post('save_consultcharge_ward', [App\Http\Controllers\HospitalController::class, 'save_consultcharge_ward'])->name('save.consultcharge.ward');

    Route::post('update_doctor_consultant_charge',[App\Http\Controllers\HospitalController::class,'update_doctor_consultant_charge'])->name('update_doctor_consultant_charge');

    Route::post('/update_hospital_consultcharge/{id}', [App\Http\Controllers\HospitalController::class, 'update_hospital_consultcharge'])->name('update.hospital_consultcharge');
    Route::get('/list_hospital_room', [App\Http\Controllers\HospitalController::class, 'list_hospital_room'])->name('list.hospital_room');
    Route::get('/get_room_types/{id}',[App\Http\Controllers\HospitalController::class,'getroomtypes'])->name('room.get-room-types');
    Route::post('/save_charges', [App\Http\Controllers\HospitalController::class, 'saveCharges'])->name('save.charges');
    Route::post('/save_hospital_room', [App\Http\Controllers\HospitalController::class, 'save_hospital_room'])->name('save.hospital_room');
    Route::post('/update_hospital_room', [App\Http\Controllers\HospitalController::class, 'update_hospital_room'])->name('update.hospital_room');
    Route::get('/roomchangeStatus',[App\Http\Controllers\HospitalController::class,'room_status_update'])->name('room.status_update');
    Route::get('/list_hospital_package', [App\Http\Controllers\HospitalController::class, 'list_hospital_package'])->name('list.hospital_package');
    Route::get('/get_packages/{id}',[App\Http\Controllers\HospitalController::class,'getpackages'])->name('room.get-packages');
    Route::post('/save_hospital_package', [App\Http\Controllers\HospitalController::class, 'save_hospital_package'])->name('save.hospital_package');
    Route::get('/hosppackagechangeStatus',[App\Http\Controllers\HospitalController::class,'package_status_update'])->name('package.status_update');
    Route::post('/update_hospital_package', [App\Http\Controllers\HospitalController::class, 'update_hospital_package'])->name('update.hospital_package');
    Route::get('/list_hospital_ward', [App\Http\Controllers\HospitalController::class, 'list_hospital_ward'])->name('list.hospital_ward');
    Route::post('/save_hospital_ward', [App\Http\Controllers\HospitalController::class, 'save_hospital_ward'])->name('save.hospital_ward');
    Route::post('/save_hospital_ward_room', [App\Http\Controllers\HospitalController::class, 'save_hospital_ward_room'])->name('save.hospital_ward_room');
    Route::post('/update_hospital_ward/{id}', [App\Http\Controllers\HospitalController::class, 'update_hospital_ward'])->name('update.hospital_ward');
    Route::post('/update-hospital-ward-details', [App\Http\Controllers\HospitalController::class, 'update_hospital_ward_details'])->name('update.hospital_ward_details');
    Route::POST('/update_hospital_bed_status', [App\Http\Controllers\HospitalController::class, 'update_hospital_bed_status'])->name('update.hospital_bed_status');
    //Hospital Floor
    Route::get('/hospital-floors', [App\Http\Controllers\HospitalController::class, 'hospital_floors'])->name('list.hospital_floors');
    Route::post('/save-hospital-floors', [App\Http\Controllers\HospitalController::class, 'save_hospital_floors'])->name('save.hospital_floors');
    Route::post('/update-hospital-floors', [App\Http\Controllers\HospitalController::class, 'update_hospital_floors'])->name('update.hospital_floors');
    Route::get('/floorchangeStatus',[App\Http\Controllers\HospitalController::class,'floor_status_update'])->name('floor.status_update');

    /* Room Rent */
     Route::get('/hospital/service/cost', [App\Http\Controllers\HospitalController::class, 'roomServiceCost'])->name('add.hospital_room_servicecost');
     Route::post('room/rent_add', [App\Http\Controllers\HospitalController::class, 'addRoomRent'])->name('room_rent');
     /* Route::get('room/rent/{id}', [App\Http\Controllers\HospitalController::class, 'editRoomRent'])
    ->name('room_rent.edit');
    Route::delete('room/rent/delete/{id}', [App\Http\Controllers\HospitalController::class, 'deleteRoomRent'])
    ->name('room_rent.delete'); */




    Route::get('/add_hospital_roomgst', [App\Http\Controllers\HospitalController::class, 'add_hospital_roomgst'])->name('add.hospital_roomgst');
    Route::post('/save_hospital_roomgst', [App\Http\Controllers\HospitalController::class, 'save_hospital_roomgst'])->name('save.hospital_roomgst');
    Route::get('/changeBedGstStatus', [App\Http\Controllers\HospitalController::class, 'room_gst_status_update'])->name('roomgst.status_update');
    Route::get('/get_hospital_roomgst_charges', [App\Http\Controllers\HospitalController::class, 'get_hospital_roomgst_charges'])->name('list.get_hospital_roomgst_charges');
    Route::post('/update_hospital_roomgst_charges', [App\Http\Controllers\HospitalController::class, 'update_hospital_roomgst_charges'])->name('list.update_hospital_roomgst_charges');


    Route::get('/add_hospital_packagegst', [App\Http\Controllers\HospitalController::class, 'add_hospital_packagegst'])->name('add.hospital_packagegst');
    Route::post('/save_hospital_packagegst', [App\Http\Controllers\HospitalController::class, 'save_hospital_packagegst'])->name('save.hospital_packagegst');
    Route::post('/update_bed_gst', [App\Http\Controllers\HospitalController::class, 'update_bed_gst'])->name('update.bed_gst');

    Route::get('/add_miscellaneous_gst', [App\Http\Controllers\HospitalController::class, 'add_miscellaneous_gst'])->name('add.miscellaneous_gst');
    Route::post('/save_miscellaneous_gst', [App\Http\Controllers\HospitalController::class, 'save_miscellaneous_gst'])->name('save.miscellaneous_gst');
    Route::get('/miscellaneousStatus',[App\Http\Controllers\HospitalController::class,'miscellaneousstatus_update'])->name('miscellaneous.status_update');
    Route::post('/update_msc_charges', [App\Http\Controllers\HospitalController::class, 'update_msc_charges'])->name('update.misc_charges');

    Route::get('/add_hospital_procedure', [App\Http\Controllers\HospitalController::class, 'add_hospital_procedure'])->name('add.hospital_procedure');
    Route::post('/save_hospital_procedure', [App\Http\Controllers\HospitalController::class, 'save_hospital_procedure'])->name('save.hospital_procedure');
    Route::post('/update_hospital_procedure', [App\Http\Controllers\HospitalController::class, 'update_hospital_procedure'])->name('update.hospital_procedure');
    Route::get('/hospital_procedure_status_update',[App\Http\Controllers\HospitalController::class,'hospital_procedure_status_update'])->name('status_update.hospital_procedurex');


    Route::get('/add_pharmacy_gst', [App\Http\Controllers\HospitalController::class, 'add_pharmacy_gst'])->name('add.pharmacy_gst');
    Route::post('/save_pharmacy_gst', [App\Http\Controllers\HospitalController::class, 'save_pharmacy_gst'])->name('save.pharmacy_gst');
    Route::post('/update_pharmacy_gst', [App\Http\Controllers\HospitalController::class, 'update_pharmacy_gst'])->name('update.pharmacy_gst');

    Route::get('/list_hospital_consumable', [App\Http\Controllers\HospitalController::class, 'list_hospital_consumable'])->name('list.hospital_consumable');
    Route::post('/save_hospital_consumable', [App\Http\Controllers\HospitalController::class, 'save_hospital_consumable'])->name('save.hospital_consumable');
    Route::post('/update_hospital_consumable', [App\Http\Controllers\HospitalController::class, 'update_hospital_consumable'])->name('update.hospital_consumable');
    Route::get('/consumablechangeStatus',[App\Http\Controllers\HospitalController::class,'consumable_status_update'])->name('consumable.status_update');

    Route::get('/system-uploaded-document-management-form',[App\Http\Controllers\HospitalController::class,'uploaded_document_management_form'])->name('upload.uploaded_document_management_form');
    Route::post('/upload-system-document',[App\Http\Controllers\HospitalController::class,'upload_system_documents'])->name('upload.upload_system_documents');
    Route::post('/remove-system-document',[App\Http\Controllers\HospitalController::class,'remove_system_documents'])->name('upload.remove_system_documents');

    Route::get('/hospital-services', [App\Http\Controllers\HospitalController::class,'hospital_services'])->name('hospital.services');
    Route::post('/procedure-toggle', [App\Http\Controllers\HospitalController::class,'toggleStatus'])->name('procedure.toggle');
    Route::post('/procedures/inline-update',[App\Http\Controllers\HospitalController::class, 'inlineUpdate'])->name('update.procedures.inline');
    Route::get('/edit-service-charges', [App\Http\Controllers\HospitalController::class, 'edit_service_charges'])->name('edit.service_charges');
    Route::post('update-er-services-charges',[App\Http\Controllers\HospitalController::class,'update_er_charges'])->name('update.er_charges');

    /* Super Admin Moved */
    Route::get('/service/category/list', [App\Http\Controllers\HospitalController::class, 'category_list'])->name('service.category.list');
    Route::post('/category/save',[App\Http\Controllers\HospitalController::class,'save_category'])->name('category.save');
    Route::post('/category/update',[App\Http\Controllers\HospitalController::class,'update_category'])->name('category.update');
    Route::post('/category/update-order', [App\Http\Controllers\HospitalController::class, 'update_category_order'])->name('category.update_order');
    Route::get('/category_test_includes/{id}',[App\Http\Controllers\HospitalController::class,'category_test_includes'])->name('include_tests.category');

    Route::get('discharge/checklist', [App\Http\Controllers\HospitalController::class, 'discharge_checklist'])->name('discharge.checklist');
    Route::post('/discharge/checklist/save',[App\Http\Controllers\HospitalController::class,'ds_checklist_save'])->name('ds_checklist.save');
    Route::get('/change-ds-status',[App\Http\Controllers\HospitalController::class,'ds_checklist_status'])->name('changeDsStatus');

    Route::get('services-charges-master-setup', [App\Http\Controllers\HospitalController::class, 'service_charges_master'])->name('hospital.service_charges_master');
    Route::post('services-charges-master-save', [App\Http\Controllers\HospitalController::class, 'save_service_charges_master'])->name('hospital.save_service_charges_master');
    Route::get('services-charges-master-export', [App\Http\Controllers\HospitalController::class, 'export_service_charges_master'])->name('hospital.export_service_charges_master');
    Route::post('services-charges-master-verify', [App\Http\Controllers\HospitalController::class, 'verify_import_charges'])->name('hospital.verify_import_charges');
    Route::post('services-charges-master-import', [App\Http\Controllers\HospitalController::class, 'import_charges'])->name('hospital.import_charges');

    Route::get('/team/list', [App\Http\Controllers\HospitalController::class, 'team_list'])->name('team.list');
    Route::post('/team/save',[App\Http\Controllers\HospitalController::class,'save_team'])->name('team.save');
    Route::get('/team-include/{id}',[App\Http\Controllers\HospitalController::class,'team_includes'])->name('include.team');
    Route::get('/update_team_includes_status',[App\Http\Controllers\HospitalController::class,'update_status_team_includes'])->name('update_include.team.status');

    Route::prefix('v2/onboard')->name('v2.')->group(function () {
        Route::get('/hospital', [App\Http\Controllers\HospitalController::class, 'hospital_onboard_forms'])->name('hospital.onboard');
        Route::post('/hospital/profile', [App\Http\Controllers\HospitalController::class, 'save_clinic_profile'])->name('hospital.save_profile');
        Route::post('/hospital/doctor/search', [App\Http\Controllers\HospitalController::class, 'search_doctor_by_mobile'])->name('hospital.doctor_search');
        Route::post('/hospital/send-otp', [App\Http\Controllers\HospitalController::class, 'send_onboard_otp'])->name('hospital.send_otp');
        Route::post('/hospital/doctor/send-otp', [App\Http\Controllers\HospitalController::class, 'send_onboard_otp'])->name('hospital.doctor_send_otp');
        Route::post('/hospital/doctor/verify-otp', [App\Http\Controllers\HospitalController::class, 'verify_doctor_otp'])->name('hospital.doctor_verify_otp');
        Route::post('/hospital/doctor/save', [App\Http\Controllers\HospitalController::class, 'save_onboard_doctors'])->name('hospital.save_doctors');
        Route::post('/hospital/doctor/send-invite', [App\Http\Controllers\HospitalController::class, 'send_doctor_invite'])->name('hospital.doctor_send_invite');
        Route::post('/hospital/user/send-otp', [App\Http\Controllers\HospitalController::class, 'send_onboard_otp'])->name('hospital.user_send_otp');
        Route::post('/hospital/user/verify-otp', [App\Http\Controllers\HospitalController::class, 'verify_user_otp'])->name('hospital.user_verify_otp');
        Route::post('/hospital/user/send-invite', [App\Http\Controllers\HospitalController::class, 'send_user_invite'])->name('hospital.user_send_invite');
        Route::post('/hospital/user/save', [App\Http\Controllers\HospitalController::class, 'save_onboard_users'])->name('hospital.save_users');
        Route::post('/hospital/user/deactivate', [App\Http\Controllers\HospitalController::class, 'deactivate_onboard_user'])->name('hospital.user_deactivate');
        Route::post('/hospital/access/save', [App\Http\Controllers\HospitalController::class, 'save_onboard_access'])->name('hospital.save_user_access');
        Route::get('/hospital/roles', [App\Http\Controllers\HospitalController::class, 'get_onboard_roles'])->name('hospital.roles');
        Route::post('/hospital/categories/save', [App\Http\Controllers\HospitalController::class, 'save_onboard_categories'])->name('hospital.save_categories');
        Route::post('/hospital/consultcharge/save', [App\Http\Controllers\HospitalController::class, 'save_onboard_consultcharges'])->name('hospital.save_consultcharge');
        Route::post('/hospital/services/save', [App\Http\Controllers\HospitalController::class, 'save_onboard_services'])->name('hospital.save_services');
        Route::post('/hospital/status', [App\Http\Controllers\HospitalController::class, 'save_onboard_status'])->name('hospital.save_onboard_status');
    });


    // *************   Invenotry Routes ************
  Route::middleware(['module-license:inventory'])->group(function () {

    Route::group(['prefix' => 'pharmacy'], function () {
      Route::get('/home', [App\Http\Controllers\InventoryController::class, 'homepage'])->name('inventory.homepage');
      Route::get('/bill', [App\Http\Controllers\InventoryController::class, 'bill'])->name('inventory.billing');
      Route::get('/challan', [App\Http\Controllers\InventoryController::class, 'challan'])->name('inventory.challan');
      Route::get('/challan-edit/{id}', [App\Http\Controllers\InventoryController::class, 'challan_edit'])->name('inventory.challan_edit');
      Route::get('/bill-edit/{id}', [App\Http\Controllers\InventoryController::class, 'bill_edit'])->name('inventory.billing_edit');
      Route::get('/get_bill_by_number', [App\Http\Controllers\InventoryController::class, 'get_bill_by_number'])->name('inventory.get_bill_by_number');
      Route::get('/get_bill_for_return', [App\Http\Controllers\InventoryController::class, 'get_bill_for_return'])->name('inventory.get_bill_for_return');
      Route::get('/get_all_bills_items_for_return', [App\Http\Controllers\InventoryController::class, 'get_all_bills_items_for_return'])->name('inventory.get_all_bills_items_for_return');
      Route::get('/fetch_recent_bills_for_quick_load_view', [App\Http\Controllers\InventoryController::class, 'fetch_recent_bills_for_quick_load_view'])->name('inventory.fetch_recent_bills_for_quick_load');
      Route::get('/fetch_recent_bills_for_quick_load', [App\Http\Controllers\InventoryController::class, 'fetch_bill_items_quick_load'])->name('inventory.fetch_bill_items_quick_load');
      Route::get('/bill-return', [App\Http\Controllers\InventoryController::class, 'bill_return'])->name('inventory.bill_return');
      Route::get('/bill-return-edit/{id}', [App\Http\Controllers\InventoryController::class, 'bill_return_edit'])->name('inventory.bill_return_edit');
      Route::post('/update-bill-return-edit', [App\Http\Controllers\InventoryController::class, 'update_bill_return_edit'])->name('inventory.update_bill_return_edit');
      Route::get('/bill-history', [App\Http\Controllers\InventoryController::class, 'bill_history'])->name('inventory.bill_history');
      Route::get('/patient-bill-history/{id?}', [App\Http\Controllers\InventoryController::class, 'patient_bill_history'])->name('inventory.patient_bill_history');
      Route::get('/payment-history', [App\Http\Controllers\InventoryController::class, 'payment_history'])->name('inventory.payment_history');
      Route::get('/bill-settlement', [App\Http\Controllers\InventoryController::class, 'bill_settlement'])->name('inventory.bill_settlement');
      Route::post('/update-bill-settlement2', [App\Http\Controllers\InventoryController::class, 'update_bill_settlement'])->name('inventory.update_bill_settlement');
      Route::post('/bulk-update-bill-settlement', [App\Http\Controllers\InventoryController::class, 'bulk_update_bill_settlement'])->name('inventory.bulk_update_bill_settlement');
      Route::get('/pharmacy-indent-request/{id}', [App\Http\Controllers\InventoryController::class, 'billing_request'])->name('inventory.billing_request');
      Route::get('/update-return-pharmacy-indent/{id}', [App\Http\Controllers\InventoryController::class, 'update_billing_request'])->name('inventory.update_billing_request');
      Route::post('/save-updated-return-pharmacy-indent', [App\Http\Controllers\InventoryController::class, 'save_updated_billing_request'])->name('inventory.save_updated_billing_request');
      Route::get('/pharmacy-indent-return-request', [App\Http\Controllers\InventoryController::class, 'billing_return_request'])->name('inventory.billing_return_request');
      Route::get('/ipd-patient/view-phamacy-list/{id}', [App\Http\Controllers\InventoryController::class, 'ipd_pharamacy_list'])->name('inventory.ipd_pharamacy_list');
      Route::get('/er-patient/view-phamacy-list/{id}', [App\Http\Controllers\InventoryController::class, 'er_pharamacy_list'])->name('inventory.er_pharamacy_list');

        /* Bill Settle */
      Route::post('/update-bill-settlement', [App\Http\Controllers\InventoryController::class, 'updateBillSettlement'])->name('inventory.updateBillSettlement');

      Route::post('/save-billing-indent-template', [App\Http\Controllers\InventoryController::class, 'save_billing_indent_template'])->name('inventory.save_billing_indent_template');

      Route::get('/check-for-pharmacy-indent', [App\Http\Controllers\InventoryController::class, 'check_for_pharmacy_indent'])->name('inventory.check_for_pharmacy_indent');

      Route::get('bill-audit-log/{id}', [App\Http\Controllers\InventoryController::class, 'bill_audit_log'])->name('inventory.bill_audit_log');

      Route::post('update-challan-status', [App\Http\Controllers\InventoryController::class, 'update_challan_status'])->name('update.challan.status');

      Route::get('/get-billing-payments/{id}', [App\Http\Controllers\InventoryController::class, 'get_billing_payments'])->name('inventory.get_billing_payments');
      Route::post('/cancel-billing/{id}', [App\Http\Controllers\InventoryController::class, 'cancel_billing'])->name('inventory.cancel_billing');
    });



    Route::group(['prefix' => 'mrd'], function () {
      Route::get('/patient-list', [App\Http\Controllers\MRDController::class, 'patient_list'])->name('mrd.patient_list');
      Route::get('/print-qr', [App\Http\Controllers\MRDController::class, 'print_qr'])->name('mrd.print_ipd_qr');

      Route::any('/automated-qr-scanner', [App\Http\Controllers\MRDController::class, 'automated_qr_scanner'])->name('mrd.automated_qr_scanner');
      Route::any('/qr-scan', [App\Http\Controllers\MRDController::class, 'qr_scan_backend'])->name('mrd.qr_scan_backend');

      Route::get('ipd/print-qr',[App\Http\Controllers\MRDController::class, 'qr_print'])->name('ipd.print_qr');
      Route::any('ipd-print-qr-sheet',[App\Http\Controllers\MRDController::class, 'qr_print_sheet'])->name('mrd.print_ipd_qr_sheet');

      Route::post('/attach-document', [App\Http\Controllers\MRDController::class, 'attach_document'])->name('mrd.attach_document');


      Route::get('/unsorted-document-list', [App\Http\Controllers\MRDController::class, 'unsorted_document_list'])->name('mrd.unsorted_document_list');
      Route::post('/update-document-tag', [App\Http\Controllers\MRDController::class, 'update_document_tag'])->name('mrd.update_document_tag');
      Route::post('/skip-document-tag', [App\Http\Controllers\MRDController::class, 'skip_document_tag'])->name('mrd.skip_document_tag');

      Route::get('/skipped-document-list', [App\Http\Controllers\MRDController::class, 'skipped_document_list'])->name('mrd.skipped_document_list');
      Route::post('/delete-document', [App\Http\Controllers\MRDController::class, 'delete_document'])->name('mrd.delete_document');
    });

    Route::group(['prefix' => 'inventory'], function () {

      Route::get('/inventory-dashoard', [App\Http\Controllers\InventoryController::class, 'index'])->name('inventory.dashboard');
      Route::get('/get_item_type_sales', [App\Http\Controllers\InventoryController::class, 'get_item_type_sales'])->name('inventory.get_item_type_sales');

      Route::post('/pharmacy/get-collection-summary', [App\Http\Controllers\InventoryController::class, 'get_collection_summary'])->name('pharmacy.get_collection_summary');

      //Billing
      Route::post('/save-billing', [App\Http\Controllers\InventoryController::class, 'save_billing'])->name('inventory.save_billing');
      Route::post('/save-convert-challan-to-billing', [App\Http\Controllers\InventoryController::class, 'save_converted_challan_to_billing'])->name('inventory.save_converted_challan_to_billing');
      Route::post('/save-challan', [App\Http\Controllers\InventoryController::class, 'save_challan'])->name('inventory.save_challan');
      Route::post('/save-return-billing', [App\Http\Controllers\InventoryController::class, 'save_bill_return'])->name('inventory.save_bill_return');
      Route::post('/save-return-billing-against-uhid-and-bill', [App\Http\Controllers\InventoryController::class, 'save_bill_return_against_uhid_and_bill'])->name('inventory.save_bill_return_against_uhid_and_bill');
      Route::post('/save-billing2', [App\Http\Controllers\InventoryController::class, 'save_billing2'])->name('inventory.save_billing2');
      Route::post('/update-billing', [App\Http\Controllers\InventoryController::class, 'update_billing'])->name('inventory.update_billing');
      Route::post('/update-challan', [App\Http\Controllers\InventoryController::class, 'update_challan'])->name('inventory.update_challan');
      Route::post('/save-billing-request', [App\Http\Controllers\InventoryController::class, 'save_billing_request'])->name('inventory.save_billing_request');
      Route::post('/save-billing-return-request', [App\Http\Controllers\InventoryController::class, 'save_billing_return_request'])->name('inventory.save_billing_return_request');
      Route::get('/show-inventory-bill', [App\Http\Controllers\InventoryController::class, 'show_inventory_bill'])->name('inventory.show_inventory_bill');

      Route::get('/show-inventory-merge-bill', [App\Http\Controllers\InventoryController::class, 'merge_show_inventory_bill'])->name('inventory.merge_show_inventory_bill');
      Route::get('/print-inventory-bill-bulk', [App\Http\Controllers\InventoryController::class, 'print_bulk_pharmacy_bills'])->name('inventory.merge_print_pharmacy_bill');
      Route::get('/print-inventory-bill-combined', [App\Http\Controllers\InventoryController::class, 'show_inventory_bill_combine'])->name('inventory.show_inventory_bill_combine');

      Route::get('/mark_billing_request_dispatched', [App\Http\Controllers\InventoryController::class, 'mark_billing_request_dispatched'])->name('inventory.mark_billing_request_dispatched');
      Route::get('/mark_indent_request_dispatched', [App\Http\Controllers\InventoryController::class, 'mark_indent_request_dispatched'])->name('inventory.mark_indent_request_dispatched');
      Route::get('/reject-billing-request', [App\Http\Controllers\InventoryController::class, 'reject_billing_request'])->name('inventory.reject_billing_request');
      Route::get('/delete-billing-request', [App\Http\Controllers\InventoryController::class, 'delete_billing_request'])->name('inventory.delete_billing_request');
      Route::get('/fetch_billing_request_items', [App\Http\Controllers\InventoryController::class, 'fetch_billing_request_items'])->name('inventory.fetch_billing_request_items');
      Route::post('/fetch_items_by_difference', [App\Http\Controllers\InventoryController::class, 'fetch_items_by_difference'])->name('inventory.fetch_items_by_difference');
      Route::get('/print_billing_request_items', [App\Http\Controllers\InventoryController::class, 'print_billing_request_items'])->name('inventory.print_billing_request_items');
      Route::get('/fetch_department_indent_items', [App\Http\Controllers\InventoryController::class, 'fetch_department_indent_items'])->name('inventory.fetch_department_indent_items');

      Route::get('/get_pending_sale_challan', [App\Http\Controllers\InventoryController::class, 'get_pending_challan_bill'])->name('inventory.get_pending_challan_bill');
      Route::get('/load_pending_sale_challan', [App\Http\Controllers\InventoryController::class, 'load_pending_sale_challan'])->name('inventory.load_pending_sale_challan');
      Route::get('/bulk_load_pending_challan_bills', [App\Http\Controllers\InventoryController::class, 'bulk_load_pending_challan_bills'])->name('inventory.bulk_load_pending_challan_bills');

      Route::get('/validate_pharmacy_clearance', [App\Http\Controllers\InventoryController::class, 'validate_pharmacy_clearance'])->name('validate_pharmacy_clearance');

      Route::get('/load_pending_patient_indent', [App\Http\Controllers\InventoryController::class, 'load_pending_patient_indent'])->name('inventory.load_pending_patient_indent');
      Route::get('/get_merged_pending_indent_items', [App\Http\Controllers\InventoryController::class, 'get_merged_pending_indent_items'])->name('inventory.get_merged_pending_indent_items');

      Route::get('/get-sale-turnover', [App\Http\Controllers\InventoryController::class, 'get_sale_turnover'])->name('inventory.get_sale_turnover');

      Route::get('/advance-bill-search', [App\Http\Controllers\InventoryController::class, 'advance_bill_search'])->name('inventory.advance_bill_search');

      Route::get('/pharmacy-indent-request', [App\Http\Controllers\InventoryController::class, 'phramacy_indent_request'])->name('inventory.phramacy_indent_request');
      Route::get('/dispatch-indent-form', [App\Http\Controllers\InventoryController::class, 'dispatch_indent_form'])->name('inventory.dispatch_indent_form');
      Route::post('/save-dispatch-indent-form', [App\Http\Controllers\InventoryController::class, 'save_dispatch_indent_form'])->name('inventory.save_dispatch_indent_form');
      // Route::get('/print-inventory-bill', [App\Http\Controllers\InventoryController::class, 'print_bill'])->name('inventory.print_bill');

      Route::get('/suppliers', [App\Http\Controllers\InventoryController::class, 'suppliers'])->name('inventory.suppliers');
      Route::get('/get-hospital-suppliers-form', [App\Http\Controllers\InventoryController::class, 'get_suppliers_form'])->name('inventory.get_suppliers_form');
      Route::post('/save-hospital-suppliers', [App\Http\Controllers\InventoryController::class, 'save_suppliers'])->name('inventory.save_suppliers');
      Route::post('/update-hospital-supplier', [App\Http\Controllers\InventoryController::class, 'update_suppliers'])->name('inventory.update_suppliers');
      Route::get('/update-hospital-supplier-status', [App\Http\Controllers\InventoryController::class, 'update_supplier_status'])->name('inventory.update_supplier_status');
      Route::post('/update-hospital-supplier-store', [App\Http\Controllers\InventoryController::class, 'update_supplier_store'])->name('inventory.update_supplier_store');
      Route::get('/get-suppliers-gst-update-form', [App\Http\Controllers\InventoryController::class, 'get_suppliers_gst_update_form'])->name('inventory.get_suppliers_gst_update_form');
      Route::get('/get-suppliers-gst-update-history', [App\Http\Controllers\InventoryController::class, 'get_suppliers_gst_update_history'])->name('inventory.get_suppliers_gst_update_history');
      Route::post('/save-supplier-gst-update-form', [App\Http\Controllers\InventoryController::class, 'save_supplier_gst_update_form'])->name('inventory.save_supplier_gst_update_form');

      Route::get('/stores', [App\Http\Controllers\InventoryController::class, 'stores'])->name('inventory.stores');
      Route::post('/save-hospital-store', [App\Http\Controllers\InventoryController::class, 'save_store'])->name('inventory.save_store');
      Route::post('/update-hospital-store', [App\Http\Controllers\InventoryController::class, 'update_store'])->name('inventory.update_store');
      Route::get('/update-hospital-store-status', [App\Http\Controllers\InventoryController::class, 'update_store_status'])->name('inventory.update_store_status');
      Route::get('/update-hospital-store-indent-status', [App\Http\Controllers\InventoryController::class, 'update_store_indent_status'])->name('inventory.update_store_indent_status');
      Route::post('/update-hospital-store-users', [App\Http\Controllers\InventoryController::class, 'update_store_users'])->name('inventory.update_store_users');

      Route::get('/manufacturers', [App\Http\Controllers\InventoryController::class, 'manufacturers'])->name('inventory.manufacturers');
      Route::post('/save-hospital-manufacturer', [App\Http\Controllers\InventoryController::class, 'save_manufacturer'])->name('inventory.save_manufacturer');
      Route::post('/update-hospital-manufacturer', [App\Http\Controllers\InventoryController::class, 'update_manufacturer'])->name('inventory.update_manufacturer');
      Route::get('/update-hospital-manufacturer-status', [App\Http\Controllers\InventoryController::class, 'update_manufacturer_status'])->name('inventory.update_manufacturer_status');

      Route::get('/items', [App\Http\Controllers\InventoryController::class, 'items'])->name('inventory.items');
      Route::get('/all-items', [App\Http\Controllers\InventoryController::class, 'all_items'])->name('inventory.all_items');
      Route::post('/save-items', [App\Http\Controllers\InventoryController::class, 'save_items'])->name('inventory.save_items');
      Route::get('/get-items-update-form', [App\Http\Controllers\InventoryController::class, 'get_item_update_form'])->name('inventory.get_item_update_form');
      Route::post('/update-items', [App\Http\Controllers\InventoryController::class, 'update_items'])->name('inventory.update_items');
      Route::get('/update-item-status', [App\Http\Controllers\InventoryController::class, 'update_item_status'])->name('inventory.update_item_status');
      Route::post('/inline-update-item', [App\Http\Controllers\InventoryController::class, 'inline_update_item'])->name('inventory.inline_update_item');
      Route::get('/delete-item', [App\Http\Controllers\InventoryController::class, 'delete_item'])->name('inventory.delete_item');

      Route::get('/get-item-count-by-hsn', [App\Http\Controllers\InventoryController::class, 'countByHsn'])->name('inventory.countByHsn');
      Route::get('/search-hsn-gst', [App\Http\Controllers\InventoryController::class, 'searchHsnGst'])->name('inventory.search_hsn_gst');
      //Bulk Item Import
      Route::post('/bulk-item-upload/import', [App\Http\Controllers\InventoryController::class, 'import_item_bulk'])->name('inventory.import_item_bulk');
      Route::get('/bulk-item-upload/review-table/{table}', [App\Http\Controllers\InventoryController::class, 'review_bulk_imported_items'])->name('inventory.review_bulk_imported_items');
      Route::get('/bulk-item-upload/process/{table}', [App\Http\Controllers\InventoryController::class, 'process_bulk_imported_items2'])->name('inventory.process_bulk_imported_items');
      Route::get('/bulk-item-upload/review/{table}', [App\Http\Controllers\InventoryController::class, 'reviewUpload'])->name('inventory.bulk_upload_item.review');
      Route::post('/bulk-item-upload/update-temp-row', [App\Http\Controllers\InventoryController::class, 'updateBulkUploadTempRow'])->name('inventory.bulk_upload_item.update_temp_row');
      // Route::post('/bulk-item-upload/retry-row', [App\Http\Controllers\InventoryController::class, 'retryRretryBulkItemUploadRowow'])->name('inventory.bulk_upload_item.retryRow');
      // Route::post('/bulk-item-upload/retry-all', [App\Http\Controllers\InventoryController::class, 'retryBulkItemUploadRowAll'])->name('inventory.bulk_upload_item.retryAll');




      Route::get('/hopital-items', [App\Http\Controllers\InventoryController::class, 'hospital_items'])->name('inventory.hospital_items');
      Route::post('/save-hospital-items', [App\Http\Controllers\InventoryController::class, 'save_hospital_items'])->name('inventory.save_hospital_items');
      Route::post('/update-hospital-item', [App\Http\Controllers\InventoryController::class, 'update_hospital_item'])->name('inventory.update_hospital_item');
      Route::get('/update-hospital-item-status', [App\Http\Controllers\InventoryController::class, 'update_hospital_item_status'])->name('inventory.update_hospital_item_status');
      Route::post('/update-hospital-item-suppliers', [App\Http\Controllers\InventoryController::class, 'update_hospital_item_suppliers'])->name('inventory.update_hospital_item_suppliers');
      Route::get('/get-hospital-item-suppliers', [App\Http\Controllers\InventoryController::class, 'get_hospital_item_suppliers'])->name('inventory.get_hospital_item_suppliers');
      Route::post('/add-hospital-item-suppliers', [App\Http\Controllers\InventoryController::class, 'add_hospital_item_suppliers'])->name('inventory.add_hospital_item_suppliers');
      Route::get('/remove-hospital-item-suppliers', [App\Http\Controllers\InventoryController::class, 'remove_hospital_item_suppliers'])->name('inventory.remove_hospital_item_suppliers');

      Route::get('/inventory-setup', [App\Http\Controllers\InventoryController::class, 'invetory_setup'])->name('inventory.invetory_setup');

      Route::post('/inventory-item-type-save', [App\Http\Controllers\InventoryController::class, 'item_type_save'])->name('inventory.item_type_save');
      Route::post('/inventory-item-type-update', [App\Http\Controllers\InventoryController::class, 'item_type_update'])->name('inventory.item_type_update');
      Route::get('/update-item-type-status', [App\Http\Controllers\InventoryController::class, 'update_item_type_status'])->name('inventory.update_item_type_status');
      Route::get('/get-item-type-attribute-form', [App\Http\Controllers\InventoryController::class, 'get_item_type_attribute'])->name('inventory.get_item_type_attribute');
      Route::post('/save-item-type-attribute-form', [App\Http\Controllers\InventoryController::class, 'save_item_type_attribute'])->name('inventory.save_item_type_attribute');

      Route::post('/inventory-item-category-save', [App\Http\Controllers\InventoryController::class, 'item_category_save'])->name('inventory.item_category_save');
      Route::post('/inventory-item-category-update', [App\Http\Controllers\InventoryController::class, 'item_category_update'])->name('inventory.item_category_update');
      Route::get('/update-item-category-status', [App\Http\Controllers\InventoryController::class, 'update_item_category_status'])->name('inventory.update_item_category_status');

      Route::post('/inventory-unit-type-save', [App\Http\Controllers\InventoryController::class, 'unit_type_save'])->name('inventory.unit_type_save');
      Route::post('/inventory-unit-type-update', [App\Http\Controllers\InventoryController::class, 'unit_type_update'])->name('inventory.unit_type_update');
      Route::get('/update-unit-type-status', [App\Http\Controllers\InventoryController::class, 'update_unit_type_status'])->name('inventory.update_unit_type_status');


      Route::post('/inventory-product-generic-save', [App\Http\Controllers\InventoryController::class, 'product_generic_save'])->name('inventory.product_generic_save');
      Route::post('/inventory-product-generic-update', [App\Http\Controllers\InventoryController::class, 'product_generic_update'])->name('inventory.product_generic_update');
      Route::get('/update-product-generic-status', [App\Http\Controllers\InventoryController::class, 'update_product_generic_status'])->name('inventory.update_product_generic_status');

      Route::post('/inventory-purchase-unit-save', [App\Http\Controllers\InventoryController::class, 'purchase_unit_save'])->name('inventory.purchase_unit_save');
      Route::post('/inventory-purchase-unit-update', [App\Http\Controllers\InventoryController::class, 'purchase_unit_update'])->name('inventory.purchase_unit_update');
      Route::get('/update-purchase-unit-status', [App\Http\Controllers\InventoryController::class, 'update_purchase_unit_status'])->name('inventory.update_purchase_unit_status');

      Route::post('/update-store-tpa-sharing', [App\Http\Controllers\InventoryController::class, 'update_store_tpa_sharing'])->name('inventory.update_store_tpa_sharing');

      //Batch Tracking
      Route::get('/inventory-batch-tracking', [App\Http\Controllers\InventoryController::class, 'batch_tracking'])->name('inventory.batch_tracking');
      Route::get('/inventory-item-tracking/{id}',[App\Http\Controllers\InventoryController::class, 'collectItemTracking'])->name('inventory.item_tracking');

      // ## Stock Management
      Route::get('/stock-management', [App\Http\Controllers\InventoryController::class, 'stock_management'])->name('inventory.stock_management');
      Route::get('/stock-issue-history', [App\Http\Controllers\InventoryController::class, 'stock_issue_history'])->name('inventory.stock_issue_history');
      Route::get('/print-issue-qr', [App\Http\Controllers\InventoryController::class, 'print_issue_qr'])->name('inventory.print_issue_qr');
      Route::get('/print-all-stocks', [App\Http\Controllers\InventoryController::class, 'print_all_stocks'])->name('inventory.print_all_stocks');
      Route::get('/download-all-stocks', [App\Http\Controllers\InventoryController::class, 'download_all_stocks'])->name('inventory.download_all_stocks');
      Route::get('/department-stock-management', [App\Http\Controllers\InventoryController::class, 'department_stock_management'])->name('inventory.department_stock_management');
      Route::get('/get-edit-rates-form', [App\Http\Controllers\InventoryController::class, 'get_edit_rates_form'])->name('inventory.get_edit_rates_form');
      Route::post('/save-rates', [App\Http\Controllers\InventoryController::class, 'save_rates'])->name('inventory.save_rates');
      Route::post('/discard-expired-item', [App\Http\Controllers\InventoryController::class, 'discard_expired_item'])->name('inventory.discard_expired_item');

      // MRN Stock QR Print
      Route::get('/print-mrn-stock-qr',[App\Http\Controllers\InventoryController::class, 'qr_mrn_stock_print'])->name('inventory.qr_mrn_stock_print');
      Route::get('/print-mrn-qr', [App\Http\Controllers\InventoryController::class, 'print_mrn_qr'])->name('inventory.print_mrn_qr');

      Route::post('/products/fetch', [App\Http\Controllers\InventoryController::class, 'products_fetch'])->name('inventory.products_fetch');
      Route::post('/products/fetch-from-stock', [App\Http\Controllers\InventoryController::class, 'products_fetch_from_stock'])->name('inventory.products_fetch_from_stock');

      // WORK Order
      Route::get('/work-order-form', [App\Http\Controllers\InventoryController::class, 'wo_form'])->name('inventory.wo_form');
      Route::get('/edit-wo/{id}', [App\Http\Controllers\InventoryController::class, 'wo_edit'])->name('inventory.wo_edit');
      Route::post('/save-work-order-form', [App\Http\Controllers\InventoryController::class, 'save_wo_form'])->name('inventory.save_wo_form');
      Route::post('/update-work-order-form', [App\Http\Controllers\InventoryController::class, 'update_wo_form'])->name('inventory.update_wo_form');
      Route::get('/status-update-wo/{id}/{status}', [App\Http\Controllers\InventoryController::class, 'update_status_wo'])->name('inventory.update_status_wo');
      Route::get('/show-wo', [App\Http\Controllers\InventoryController::class, 'show_wo'])->name('inventory.show_wo');

      // Purchase Order
      Route::get('/purchase-order-form', [App\Http\Controllers\InventoryController::class, 'po_form'])->name('inventory.po_form');
      Route::get('/edit-po/{id}', [App\Http\Controllers\InventoryController::class, 'po_edit'])->name('inventory.po_edit');
      Route::get('/get-purchase-order-form', [App\Http\Controllers\InventoryController::class, 'get_po_form'])->name('inventory.get_po_form');
      Route::get('/get-purchase-order-form-item-details', [App\Http\Controllers\InventoryController::class, 'get_po_form_item_details'])->name('inventory.get_po_form_item_details');
      Route::post('/save-purchase-order-form', [App\Http\Controllers\InventoryController::class, 'save_po_form'])->name('inventory.save_po_form');
      Route::post('/update-purchase-order-form', [App\Http\Controllers\InventoryController::class, 'update_po_form'])->name('inventory.update_po_form');
      Route::get('/status-update-po/{id}/{status}', [App\Http\Controllers\InventoryController::class, 'update_status_po'])->name('inventory.update_status_po');
      Route::get('/show-po', [App\Http\Controllers\InventoryController::class, 'show_po'])->name('inventory.show_po');

      // TODO: Remove later as created for RAMA issue query
      Route::get('/grn-qty-override', [App\Http\Controllers\InventoryController::class, 'grnQtyOverrideView'])->name('inventory.grn_qty_override_view');
      Route::get('/fetch-grn-override-details', [App\Http\Controllers\InventoryController::class, 'fetchGrnOverrideDetails'])->name('inventory.fetch_grn_override_details');
      Route::post('/save-grn-qty-override', [App\Http\Controllers\InventoryController::class, 'saveGrnQtyOverride'])->name('inventory.save_grn_qty_override');

      // Admin Tools / Utilities
      Route::get('/tools', [App\Http\Controllers\InventoryController::class, 'toolsIndex'])->name('inventory.tools');
      Route::get('/indent-billing-tracking', [App\Http\Controllers\InventoryController::class, 'indentBillingTrackingPage'])->name('inventory.indent_billing_tracking');
      Route::get('/indent-billing-tracking/ipd-list', [App\Http\Controllers\InventoryController::class, 'indentBillingTrackingIpdList'])->name('inventory.indent_billing_tracking_ipd_list');
      Route::get('/indent-billing-tracking/data', [App\Http\Controllers\InventoryController::class, 'indentBillingTrackingData'])->name('inventory.indent_billing_tracking_data');
      Route::get('/change-grn-store', [App\Http\Controllers\InventoryController::class, 'changeGrnStoreView'])->name('inventory.change_grn_store_view');
      Route::get('/fetch-grn-store-details', [App\Http\Controllers\InventoryController::class, 'fetchGrnStoreDetails'])->name('inventory.fetch_grn_store_details');
      Route::get('/preview-new-grn-number', [App\Http\Controllers\InventoryController::class, 'previewNewGrnNumber'])->name('inventory.preview_new_grn_number');
      Route::post('/save-change-grn-store', [App\Http\Controllers\InventoryController::class, 'saveChangeGrnStore'])->name('inventory.save_change_grn_store');
      Route::get('/bill-gst-update', [App\Http\Controllers\InventoryController::class, 'billGstUpdateView'])->name('inventory.bill_gst_update_view');
      Route::get('/fetch-bill-gst-details', [App\Http\Controllers\InventoryController::class, 'fetchBillGstDetails'])->name('inventory.fetch_bill_gst_details');
      Route::post('/save-bill-gst-update', [App\Http\Controllers\InventoryController::class, 'saveBillGstUpdate'])->name('inventory.save_bill_gst_update');
      Route::get('/billing-price-fix', [App\Http\Controllers\InventoryController::class, 'billingPriceFixView'])->name('inventory.billing_price_fix');
      Route::get('/fetch-billing-price-fix-data', [App\Http\Controllers\InventoryController::class, 'fetchBillingPriceFixData'])->name('inventory.fetch_billing_price_fix_data');
      Route::post('/revert-billing-item-price', [App\Http\Controllers\InventoryController::class, 'revertBillingItemPrice'])->name('inventory.revert_billing_item_price');

      // Goods Received Notes
      Route::get('/goods-received-form', [App\Http\Controllers\InventoryController::class, 'grn_form'])->name('inventory.grn_form');
      Route::get('/edit-grn/{id}', [App\Http\Controllers\InventoryController::class, 'grn_edit'])->name('inventory.grn_edit');
      Route::get('/get-goods-received-form', [App\Http\Controllers\InventoryController::class, 'get_grn_form'])->name('inventory.get_grn_form');
      Route::post('/save-goods-received-form', [App\Http\Controllers\InventoryController::class, 'save_grn_form'])->name('inventory.save_grn_form');
      Route::post('/update-goods-received-form', [App\Http\Controllers\InventoryController::class, 'update_grn_form'])->name('inventory.update_grn_form');
      Route::get('/show-grn', [App\Http\Controllers\InventoryController::class, 'show_grn'])->name('inventory.show_grn');
      Route::get('/delete-grn', [App\Http\Controllers\InventoryController::class, 'delete_grn'])->name('inventory.delete_grn');


      Route::get('/check-supplier-bill-exist', [App\Http\Controllers\InventoryController::class, 'check_supplier_bill_exist'])->name('inventory.check_supplier_bill_exist');

      Route::get('/get-recent-items-sales', [App\Http\Controllers\InventoryController::class, 'get_recent_item_sales'])->name('inventory.get_recent_item_sales');
      Route::get('/get-recent-items-po', [App\Http\Controllers\InventoryController::class, 'get_recent_item_po'])->name('inventory.get_recent_item_po');
      Route::get('/get-recent-items-grn', [App\Http\Controllers\InventoryController::class, 'get_recent_item_grns'])->name('inventory.get_recent_item_grns');
      Route::get('/get-po-items-for-grn', [App\Http\Controllers\InventoryController::class, 'get_po_item_for_grns'])->name('inventory.get_po_item_for_grns');

      Route::get('/get-grn-items-for-grn', [App\Http\Controllers\InventoryController::class, 'get_grn_item_for_grn_return'])->name('inventory.get_grn_item_for_grn_return');
      Route::get('/goods-return-form', [App\Http\Controllers\InventoryController::class, 'grn_return_form'])->name('inventory.grn_return_form');
      Route::post('/save-grn-return-form', [App\Http\Controllers\InventoryController::class, 'save_grn_return_form'])->name('inventory.save_grn_return_form');
      Route::get('/edit-grn-return/{id}', [App\Http\Controllers\InventoryController::class, 'grn_return_edit'])->name('inventory.grn_return_edit');
      Route::post('/update-goods-return-form', [App\Http\Controllers\InventoryController::class, 'update_grn_return_form'])->name('inventory.update_grn_return_form');
      Route::get('/show-grn-return', [App\Http\Controllers\InventoryController::class, 'show_grn_return'])->name('inventory.show_grn_return');


      Route::get('/payment-voucher-form', [App\Http\Controllers\InventoryController::class, 'payment_voucher_form'])->name('inventory.payment_voucher_form');
      Route::post('/save-payment-voucher-form', [App\Http\Controllers\InventoryController::class, 'save_payment_voucher_form'])->name('inventory.save_payment_voucher_form');

      Route::get('/payment-voucher-list', [App\Http\Controllers\InventoryController::class, 'payment_voucher_list'])->name('inventory.payment_voucher_list');

      Route::get('/payment-voucher-edit/{id}', [App\Http\Controllers\InventoryController::class, 'payment_voucher_form_edit'])->name('inventory.payment_voucher_form_edit');
      Route::post('/update-payment-voucher-form', [App\Http\Controllers\InventoryController::class, 'update_save_payment_voucher_form'])->name('inventory.update_payment_voucher_form');
      Route::get('/payment-voucher-delete/{id}', [App\Http\Controllers\InventoryController::class, 'payment_voucher_delete'])->name('inventory.payment_voucher_delete');

      Route::get('/get_credit_bill_of_supplier', [App\Http\Controllers\InventoryController::class, 'get_credit_bill_of_supplier'])->name('inventory.get_credit_bill_of_supplier');


      Route::get('/receipt-voucher-entry',[App\Http\Controllers\InventoryController::class, 'receipt_voucher_entry'])->name('inventory.receipt_voucher_entry');
      Route::get('/get_credit_bills_of_patient', [App\Http\Controllers\InventoryController::class, 'get_credit_bill_of_patient'])->name('inventory.get_credit_bill_of_patient');
      Route::post('/save-receipt-voucher-form', [App\Http\Controllers\InventoryController::class, 'save_receipt_voucher_form'])->name('inventory.save_receipt_voucher_form');



      Route::get('/get-department-stock-issue-form', [App\Http\Controllers\InventoryController::class, 'get_stock_issue_form'])->name('inventory.get_stock_issue_form');
      Route::post('/save-department-stock-issue-form', [App\Http\Controllers\InventoryController::class, 'save_stock_issue_form'])->name('inventory.save_stock_issue_form');
      Route::get('/send-back-indent-to-department', [App\Http\Controllers\InventoryController::class, 'send_back_indent'])->name('inventory.send_back_indent');

      Route::get('/get-department-stock-receive-form', [App\Http\Controllers\InventoryController::class, 'get_stock_receive_form'])->name('inventory.get_stock_receive_form');
      Route::post('/save-department-stock-receive-form', [App\Http\Controllers\InventoryController::class, 'save_stock_receive_form'])->name('inventory.save_stock_receive_form');

      Route::get('/get-stock-adjustment-form', [App\Http\Controllers\InventoryController::class, 'get_stock_adjustment_form'])->name('inventory.get_stock_adjustment_form');
      Route::post('/save-stock-adjustment-form', [App\Http\Controllers\InventoryController::class, 'save_stock_adjustment_form'])->name('inventory.save_stock_adjustment_form');
      Route::get('/get-stock-adjustment-detail', [App\Http\Controllers\InventoryController::class, 'get_stock_adjustment_detail'])->name('inventory.get_stock_adjustment_details');
      Route::get('/approve-stock-adjustment/{id}', [App\Http\Controllers\InventoryController::class, 'approve_stock_adjustment'])->name('inventory.approve_stock_adjustment');
      Route::get('/reject-stock-adjustment/{id}', [App\Http\Controllers\InventoryController::class, 'reject_stock_adjustment'])->name('inventory.reject_stock_adjustment');
      Route::get('/get-consumption-logs-for-order', [App\Http\Controllers\InventoryController::class, 'get_consumption_logs_for_order'])->name('inventory.get_consumption_logs_for_order');
      Route::get('/get-stock-adjustment-edit-form/{id}', [App\Http\Controllers\InventoryController::class, 'get_stock_adjustment_edit_form'])->name('inventory.get_stock_adjustment_edit_form');
      Route::post('/update-stock-adjustment-form/{id}', [App\Http\Controllers\InventoryController::class, 'update_stock_adjustment_form'])->name('inventory.update_stock_adjustment_form');

      //Add stock in store
      Route::get('/get-add-item-to-store-form', [App\Http\Controllers\InventoryController::class, 'add_item_to_store_form'])->name('inventory.add_item_to_store_form');
      Route::post('/save-stock-to-store', [App\Http\Controllers\InventoryController::class, 'save_stock_to_store'])->name('inventory.save_stock_to_store');

      // ## DEPARTMENT STOCK MANAGEMENT
      Route::get('/department-stock-management', [App\Http\Controllers\InventoryController::class, 'department_stock_management'])->name('inventory.department_stock_management');

      Route::get('/parent-tracking', [App\Http\Controllers\InventoryController::class, 'parent_tracking_list'])->name('inventory.parent_tracking_list');
      Route::post('/parent-tracking/resolve', [App\Http\Controllers\InventoryController::class, 'resolve_parent_inventory_ajax'])->name('inventory.resolve_parent_inventory_ajax');

      // Stock Indent
      Route::get('/stock-indent-form', [App\Http\Controllers\InventoryController::class, 'indent_form'])->name('inventory.indent_form');
      Route::get('/edit-stock-indent-form/{id}', [App\Http\Controllers\InventoryController::class, 'indent_form_edit'])->name('inventory.indent_form_edit');
      Route::get('/get-stock-indent-form', [App\Http\Controllers\InventoryController::class, 'get_indent_form'])->name('inventory.get_indent_form');
      Route::post('/save-stock-indent-form', [App\Http\Controllers\InventoryController::class, 'save_indent_form'])->name('inventory.save_indent_form');
      Route::get('/status-update-indent/{id}/{status}', [App\Http\Controllers\InventoryController::class, 'update_status_indent'])->name('inventory.update_status_indent');
      Route::get('/get-indent-detail', [App\Http\Controllers\InventoryController::class, 'get_indent_detail'])->name('inventory.get_indent_detail');

      // Item Returns Routes
      Route::get('/item-return-form', [App\Http\Controllers\InventoryController::class, 'item_return_form'])->name('inventory.item_return_form');
      Route::get('/get-item-return-form', [App\Http\Controllers\InventoryController::class, 'get_item_return_form'])->name('inventory.get_item_return_form');
      Route::post('/save-item-return-form', [App\Http\Controllers\InventoryController::class, 'save_item_return_form'])->name('inventory.save_item_return_form');
      Route::get('/status-update-item-return/{id}/{status}', [App\Http\Controllers\InventoryController::class, 'update_status_item_return'])->name('inventory.update_status_item_return');
      Route::get('/get-item-return-details', [App\Http\Controllers\InventoryController::class, 'get_item_return_detail'])->name('inventory.get_item_return_detail');
      Route::get('/get-return-detail', [App\Http\Controllers\InventoryController::class, 'get_return_detail'])->name('inventory.get_return_detail');
      Route::get('/status-update-return/{id}/{status}', [App\Http\Controllers\InventoryController::class, 'update_status_return'])->name('inventory.update_status_return');
      Route::get('/edit-stock-return-form/{id}', [App\Http\Controllers\InventoryController::class, 'return_form_edit'])->name('inventory.return_form_edit');
      Route::post('/update-item-return-form/{id}', [App\Http\Controllers\InventoryController::class, 'update_item_return_form'])->name('inventory.update_item_return_form');

      Route::get('/patient-item-return-form/{id}', [App\Http\Controllers\InventoryController::class, 'patient_item_return_form'])->name('inventory.patient_item_return_form');
      Route::get('/get-issued-items-to-patient', [App\Http\Controllers\InventoryController::class, 'get_issued_items_to_patient'])->name('inventory.get_issued_items_to_patient');
      Route::get('/get-available-stock-for-patient', [App\Http\Controllers\InventoryController::class, 'get_available_stock_for_billing'])->name('inventory.get_issued_stock_for_patient');
      Route::get('/get-issued-stock-for-patient', [App\Http\Controllers\InventoryController::class, 'get_issued_stock_for_patient'])->name('get.issued_stock_for_patient');
      Route::get('/get-issue-qty-for-patient',[App\Http\Controllers\InventoryController::class, 'get_soled_item_qty'])->name('get.get_soled_item_qty');

      Route::get('sale-batches-for-patient',[App\Http\Controllers\InventoryController::class,'get_billed_item_batch'])->name('get.billed_item_batches');
      Route::post('save-patient-item-return',[App\Http\Controllers\InventoryController::class,'save_patient_item_return'])->name('inventory.save_patient_item_return_form');
      Route::get('/fetch_billing_request_items_return', [App\Http\Controllers\InventoryController::class, 'fetch_billing_request_items_return'])->name('inventory.fetch_billing_request_items_return');


      // Helper Routes : Get Item Data For Select Option
      Route::get('/get-item-type-attributes', [App\Http\Controllers\InventoryController::class, 'get_item_type_attributes'])->name('inventory.get_item_type_attributes');
      Route::get('/get-inventory-items', [App\Http\Controllers\InventoryController::class, 'get_inventory_items'])->name('inventory.get_inventory_items');
      Route::get('/get-available-inventory-items', [App\Http\Controllers\InventoryController::class, 'get_available_inventory_items'])->name('inventory.get_available_inventory_items');
      Route::get('/render-item-attributes-list', [App\Http\Controllers\InventoryController::class, 'render_available_item_attribute'])->name('inventory.render_available_item_attribute');
      Route::get('/get-available-stock-items-list', [App\Http\Controllers\InventoryController::class, 'get_available_stock_for_billing'])->name('inventory.get_available_stock_for_billing');
      Route::get('/get-stock-items-availability', [App\Http\Controllers\InventoryController::class, 'get_stock_items_availability'])->name('inventory.get_stock_items_availability');

      Route::get('/get_supplier_ledger_details', [App\Http\Controllers\InventoryController::class, 'get_supplier_ledger_details'])->name('inventory.get_supplier_ledger_details');

      // Ledger Management
      Route::get('/ledgers', [App\Http\Controllers\InventoryController::class, 'ledgers'])->name('inventory.ledgers');
      Route::post('/save-ledgers', [App\Http\Controllers\InventoryController::class, 'save_ledgers'])->name('inventory.ledgers.store');
      Route::get('/get-ledger-form', [App\Http\Controllers\InventoryController::class, 'get_ledger_form'])->name('inventory.get_ledger_form');
      Route::get('/delete-ledger', [App\Http\Controllers\InventoryController::class, 'delete_ledgers'])->name('inventory.ledgers.delete');
      Route::get('/manage-ledgers',[App\Http\Controllers\InventoryController::class, 'manage_ledgers'])->name('manage.ledgers');

      Route::post('/save-ledger-entry', [App\Http\Controllers\InventoryController::class, 'save_ledger_entry'])->name('inventory.save_ledger_entry');

      Route::get('/ledger-tag-detail', [App\Http\Controllers\InventoryController::class, 'ledger_tag_details'])->name('inventory.ledger_tag_details');
      Route::get('/ledger-detail', [App\Http\Controllers\InventoryController::class, 'ledger_details'])->name('inventory.ledger_details');
      Route::get('/outstanding-bill-detail', [App\Http\Controllers\InventoryController::class, 'outstanding_bill_details'])->name('inventory.outstanding_bill_details');


      Route::get('/stock-mismatch-management', [App\Http\Controllers\InventoryController::class, 'stock_mismatch_management'])->name('inventory.stock_mismatch_management');
      Route::post('/stock-mismatch-management-fix-all', [App\Http\Controllers\InventoryController::class, 'stock_mismatch_management_fix_all'])->name('inventory.stock_mismatch_management_fix_all');


      //QR Routes
      Route::get('/print-qr', [App\Http\Controllers\InventoryController::class, 'print_qr'])->name('inventory.print_qr');
      Route::get('/load-scanned-inventory', [App\Http\Controllers\InventoryController::class, 'load_scanned_inventory'])->name('inventory.load_scanned_inventory');
      Route::get('/load-inventory-with-code', [App\Http\Controllers\InventoryController::class, 'load_inventory_with_code'])->name('inventory.load_inventory_with_code');



      Route::get('/reports', [App\Http\Controllers\InventoryController::class, 'inventory_reports'])->name('inventory.invetory_reports');

      /* Mass Item Mapping */
      Route::get('/item_mapping', [App\Http\Controllers\InventoryController::class, 'item_mapping'])->name('inventory.item_mapping');
      Route::get('/search-items', [App\Http\Controllers\InventoryController::class, 'searchItems'])->name('inventory.search-items');
      Route::post('/inventory/save-brand-item-mapping', [App\Http\Controllers\InventoryController::class, 'saveBrandItemMapping'])->name('inventory.save-brand-item-mapping');
      Route::post('/inventory/save-frequency-mapping', [App\Http\Controllers\InventoryController::class, 'saveFrequencyMapping'])->name('inventory.save-frequency-mapping');


      Route::get('restore-received-qty', [InventoryController::class, 'restoreReceivedQtyPreview'])->name('inventory.restore.preview');

      Route::post('restore-dpt-qty',[App\Http\Controllers\InventoryController::class, 'restore_dpt_qty'])->name('inventory.restore.update');
    });

  });





  // *************   Appointments Routes ************
  Route::middleware(['module-license:appointment'])->group(function () {
    Route::group(['prefix' => 'appointment'], function () {

      Route::get('/',[AppointmentController::class,'index'])->name('appointment.dashboard');
      Route::post('/send-appointment-link',[AppointmentController::class,'send_appointment_link'])->name('appointment.send_appointment_link');
      Route::get('/book',[AppointmentController::class,'book'])->name('appointment.book');
      Route::get('booking-history',[AppointmentController::class,'booking_history'])->name('appointment.booking_history');
      Route::get('get-available-slots',[AppointmentController::class,'get_timing_slot_layout'])->name('booking.get_timing_slot_layout');
      Route::get('get-date-schedule',[AppointmentController::class,'get_date_schedule'])->name('booking.get_date_schedule');
      // Route::get('booking',[AppointmentController::class,'book'])->name('appointment.book');
      Route::get('get-appointments',[AppointmentController::class,'get_appointments'])->name('appointments.get_appointments');
      Route::get('get-appointments-custom-search',[AppointmentController::class,'get_appointment_custom_search'])->name('appointments.get_appointment_custom_search');
      Route::post('book-appointments',[AppointmentController::class,'book_now'])->name('booking.book_now');
      Route::post('save-appointment-booking',[AppointmentController::class,'save_booking'])->name('booking.save_booking');
      Route::get('get-patient-detail-by-number',[AppointmentController::class,'get_patient_details'])->name('booking.get_patient_details');
      Route::post('create-patient',[AppointmentController::class,'create_patient'])->name('booking.create_patient');

      Route::get('timing-slot',[AppointmentController::class,'timing_slots'])->name('timing-slot.index');
      Route::get('get-timing-slot-layout',[AppointmentController::class,'get_doctor_timing_slot_layout'])->name('timing-slot.get_timing_slot_layout');
      Route::post('save-timing-slot', [AppointmentController::class,'store'])->name('timing-slot.store');
      // Route::post('update-timing-slot', [AppointmentController::class,'update'])->name('timing-slot.update');
      Route::get('timing-slot-status-update', [AppointmentController::class,'status_update'])->name('timing-slot.status_update');
      Route::get('timing-slot-get-doc-list', [AppointmentController::class,'get_doctors'])->name('timing-slot.get_doctors');
      Route::post('save-doc-slot-interval', [AppointmentController::class,'save_doc_slot_interval'])->name('timing-slot.save_doc_slot_interval');
      Route::get('doc-timing-slot-form', [AppointmentController::class,'get_timing_slots_form'])->name('timing-slot.get_timing_slots_form');
      Route::post('save-doc-timing-slot-form', [AppointmentController::class,'save_timing_slots_form'])->name('timing-slot.save_timing_slots_form');
      Route::get('update-status-doc-timing-slot', [AppointmentController::class,'update_status_timing_slots'])->name('timing-slot.update_status_timing_slots');
      Route::get('delete-doc-timing-slot', [AppointmentController::class,'delete_timing_slots'])->name('timing-slot.delete_timing_slots');

      Route::get('appointments',[AppointmentController::class,'appointments'])->name('appointments.index');
      Route::get('get_appointment_attatchment_form',[AppointmentController::class,'get_appointment_attatchment_form'])->name('appointments.get_appointment_attatchment_form');
      Route::post('save-appointment-attachments',[AppointmentController::class,'save_appointment_attachments'])->name('appointments.save_appointment_attachments');
      Route::get('delete_appointment_attachments',[AppointmentController::class,'delete_appointment_attachments'])->name('appointments.delete_appointment_attachments');
      // MARK VISITED
      Route::get('get_mark_visit_form',[AppointmentController::class,'get_mark_visit_form'])->name('appointments.get_mark_visit_form');
      Route::post('save-appointment-visit-mark',[AppointmentController::class,'save_mark_visit_form'])->name('appointments.save_mark_visit_form');
      // CANCELLATION
      Route::get('get_appointment_cancellation_form',[AppointmentController::class,'get_appointment_cancellation_form'])->name('appointments.get_appointment_cancellation_form');
      Route::post('save_appointment_cancellation_form',[AppointmentController::class,'save_appointment_cancellation_form'])->name('appointments.save_appointment_cancellation_form');
      // RESCHEDULE
      Route::get('get_appointment_reschedule_form',[AppointmentController::class,'get_appointment_reschedule_form'])->name('appointments.get_appointment_reschedule_form');
      Route::post('save_appointment_reschedule_form',[AppointmentController::class,'save_appointment_reschedule_form'])->name('appointments.save_appointment_reschedule_form');



      Route::get('doctor-leaves',[AppointmentController::class,'doctor_leaves'])->name('doctor.doctor_leaves');
      Route::post('save-doctor-leave', [AppointmentController::class,'save_doctor_leaves'])->name('doctor.save_doctor_leaves');
      Route::get('delete-doctor-leave/{id}', [AppointmentController::class,'delete_doctor_leaves'])->name('doctor.delete_doctor_leaves');
      Route::get('check-follow-up-availability', [AppointmentController::class,'check_doctor_follow_up_availability'])->name('doctor.check_follow_up_availability');

      Route::get('doctor-visibility-order',[AppointmentController::class,'doctor_visibility_order'])->name('doctor.doctor_visibility_order');
      Route::post('save-doctor-visibility-order', [AppointmentController::class,'save_doctor_visibility_order'])->name('doctor.save_doctor_visibility_order');

      Route::get('patients-list',[PatientController::class,'index'])->name('patients.index');

      Route::get('message-logs',[AppointmentController::class,'message_logs'])->name('appointments.message_log');



    // Route::get('group',[GroupController::class,'index'])->name('group.index');
    // Route::post('save-group', [GroupController::class,'store'])->name('group.store');
    // Route::post('update-group', [GroupController::class,'update'])->name('group.update');
    // Route::get('group-status-update', [GroupController::class,'status_update'])->name('group.status_update');



    });
  });


    // *************   Reports Hospital Level Routes ************
    //Reports Routes
    Route::get('/reports',[\App\Http\Controllers\ReportController::class,'index'])->name('reports');
    Route::get('/reports/overall-report',[\App\Http\Controllers\ReportController::class,'overall_report'])->name('reports.overall');
    Route::get('/reports/customized-report',[\App\Http\Controllers\ReportController::class,'opd_report'])->name('reports.opd');
    Route::get('/reports/customized-report2',[\App\Http\Controllers\ReportController::class,'ipd_report'])->name('reports.ipd');
    Route::get('/reports/collection-report',[\App\Http\Controllers\ReportController::class,'collection_report'])->name('reports.collection');
    Route::get('/reports/doctor-report',[\App\Http\Controllers\ReportController::class,'doctor_report'])->name('reports.doctor.overview2');
    Route::get('/reports/whatsapp-sms-report',[\App\Http\Controllers\ReportController::class,'wp_sms_report'])->name('reports.wp_sms');
    Route::get('/reports/leads-wise-report',[\App\Http\Controllers\ReportController::class,'leads_report'])->name('reports.leads');
    Route::get('/reports/lab-report',[\App\Http\Controllers\ReportController::class,'lab_reports'])->name('reports.lab');
    Route::get('/reports/hr-report',[\App\Http\Controllers\ReportController::class,'hr_reports'])->name('reports.hr');

    Route::get('/reports/ledger',[\App\Http\Controllers\ReportController::class,'ledger'])->name('reports.ledger');



    /* Dashboard Print Payments Detail Summary */
    Route::get('/reports/print-payments-detail-summary',[\App\Http\Controllers\ReportController::class,'printPaymentsDetailSummary'])->name('reports.print_detail_summary');


    // *************   Sharing Calculation Routes ************
    Route::get('/reports/sharing-calculations-formula',[\App\Http\Controllers\ReportController::class,'sharing_calculations_formula'])->name('reports.sharing_calculations_formula');
    Route::post('/validate_sharing_formula',[App\Http\Controllers\ReportController::class, 'validate_sharing_formula'])->name('reports.validate_sharing_formula');
    Route::post('/save_lab_sharing_formula',[App\Http\Controllers\ReportController::class, 'save_lab_sharing_formula'])->name('reports.save_lab_sharing_formula');
    Route::post('/save_doctor_sharing_formula',[App\Http\Controllers\ReportController::class, 'save_doctor_sharing_formula'])->name('reports.save_doctor_sharing_formula');

    Route::group(['prefix' => 'settings'], function () {

        // *************   Modules Permission Routes ************
        //Roles Route
        Route::get('/roles',[\App\Http\Controllers\RoleController::class,'index'])->name('roles');
        Route::post('/save-roles',[\App\Http\Controllers\RoleController::class,'store'])->name('roles.save');
        Route::get('/roles/edit/{id}',[\App\Http\Controllers\RoleController::class,'edit'])->name('roles.edit');
        Route::get('/roles/update-status',[\App\Http\Controllers\RoleController::class,'toggle_status'])->name('roles.status_update');
        Route::post('/roles/update/{id}',[\App\Http\Controllers\RoleController::class,'update'])->name('roles.update');
        Route::delete('/destroy-role/{id}',[\App\Http\Controllers\RoleController::class,'destroy'])->name('roles.destroy');


        //ModuleRolePermissions
        Route::resource('role-permissions',\App\Http\Controllers\ModuleRolePermissionController::class);
        Route::get('role-permissions-update',[\App\Http\Controllers\ModuleRolePermissionController::class,'update_role_permission'])->name('update_role_permission');
        Route::get('flush-role-permissions',[\App\Http\Controllers\ModuleRolePermissionController::class,'flush_role_permission'])->name('flush_role_permission');

        // Copy Permission
        // Route::get('per/module',[\App\Http\Controllers\ModuleRolePermissionController::class,'copyPermission'])->name('per_module');
        // Route::post('per/module/copy',[\App\Http\Controllers\ModuleRolePermissionController::class, 'storeCopyPermission'])->name('copy_permission_store');

        Route::get('per/module',[\App\Http\Controllers\ModuleRolePermissionController::class, 'copyPermission'])->name('per_module');
        Route::post('per/module/copy',[\App\Http\Controllers\ModuleRolePermissionController::class, 'storeCopyPermission'])->name('copy_permission_store');

        // Hospital Settings Routes
        Route::get('/settings', [App\Http\Controllers\HospitalController::class, 'settings'])->name('settings');
        Route::get('/tools', [App\Http\Controllers\HospitalController::class, 'toolsIndex'])->name('settings.tools');
        Route::get('/tools/document-tags', [App\Http\Controllers\HospitalController::class, 'documentTagsTool'])->name('settings.tools.document_tags');
        Route::post('/save-hosiptal-settings', [App\Http\Controllers\HospitalController::class, 'save_settings'])->name('save.hospital_settings');
        Route::post('/save-hosiptal-setting-data', [App\Http\Controllers\HospitalController::class, 'save_setting_data'])->name('save.hospital_setting_data');
        Route::post('/delete-hospital-setting-data', [App\Http\Controllers\HospitalController::class, 'delete_setting_data'])->name('delete.hospital_setting_data');
        Route::post('/save-hosiptal-theme-setting-data', [App\Http\Controllers\HospitalController::class, 'save_theme_setting_data'])->name('save.save_theme_setting_data');
        Route::post('/version_settings', [App\Http\Controllers\HospitalController::class, 'version_settings'])->name('version_settings');

        // ABDM Settings Routes
        Route::post('/save-abdm-settings', [App\Http\Controllers\HospitalController::class, 'save_abdm_settings'])->name('save.abdm_settings');
        Route::post('/update-doctor-hpr-id', [App\Http\Controllers\HospitalController::class, 'update_doctor_hpr_id'])->name('update.doctor_hpr_id');

        // SMS Settings Routes
        Route::get('/sms-settings', [App\Http\Controllers\HospitalController::class, 'sms_settings'])->name('sms_settings');
        Route::get('/sms_setting_status_update', [App\Http\Controllers\HospitalController::class, 'sms_setting_status_update'])->name('sms_setting_status_update');
        Route::post('/sms_key_setting_update', [App\Http\Controllers\HospitalController::class, 'sms_key_setting_update'])->name('sms_key_setting_update');
        Route::get('/sms_key_own_update', [App\Http\Controllers\HospitalController::class, 'sms_key_own_update'])->name('sms_key_own_update');
        Route::get('/whatsapp_status_update', [App\Http\Controllers\HospitalController::class, 'whatsapp_status_update'])->name('whatsapp_status_update');

    // Clinical Setting Routes
    Route::group(['prefix' => 'clinical-settings'], function () {
        Route::get('/', [App\Http\Controllers\HospitalController::class, 'clinical_settings'])->name('clinical_settings');
        Route::get('/ipd/consent', [App\Http\Controllers\HospitalController::class, 'ipdConsentForm'])->name('ipd.consent');
        Route::get('/form-update/{id}', [App\Http\Controllers\HospitalController::class, 'hospital_form_template_update'])->name('hospital_form_template_update');
        Route::post('/save-form', [App\Http\Controllers\HospitalController::class, 'save_hospital_form_template'])->name('save_hospital_form_template');
        Route::get('/get-template-variable-section/{id}', [App\Http\Controllers\HospitalController::class, 'get_form_template_variable_section'])->name('get_form_template_variable_section');
        Route::post('/add-template-variable', [App\Http\Controllers\HospitalController::class, 'add_form_template_variable'])->name('add_form_template_variable');
        Route::get('/remove-template-variable', [App\Http\Controllers\HospitalController::class, 'remove_form_template_variable'])->name('remove_form_template_variable');

        Route::get('/ipd/estimate', [App\Http\Controllers\HospitalController::class, 'ipdEstimate'])->name('ipd.estimate');
        Route::post('/consent-form/store', [App\Http\Controllers\HospitalController::class, 'storeConsentForm'])->name('consent.form.store');
        Route::get('consent/search', [App\Http\Controllers\HospitalController::class, 'searchConsent'])->name('consent.search');
        Route::get('consent/form/data', [App\Http\Controllers\HospitalController::class, 'getClinicalConsentForm'])->name('consent.form.data');
        Route::post('consent/update-note',[App\Http\Controllers\HospitalController::class, 'updateConsentNote'])->name('consent.update.note');
        Route::get('getconsentdetails/{id}',[App\Http\Controllers\HospitalController::class, 'getFormConsentPatientdetails'])->name('getconsentdetails');
        Route::get('consent/template',[App\Http\Controllers\HospitalController::class, 'print_admission_consent_form'])->name('consent_form_template');

        // Estimage Cost
         Route::post('/estimate-cost-form/store', [App\Http\Controllers\HospitalController::class, 'storeEstimateCostForm'])->name('estimate.cost.store');

        Route::post('ipd-consent-active', [App\Http\Controllers\HospitalController::class, 'consentFormStatus'])->name('ipd.consent.active');
    });


        // Print Settings Routes
        Route::get('/print-settings', [App\Http\Controllers\HospitalController::class, 'print_settings'])->name('print_settings');
        Route::post('/save-lab-test-print-settings', [App\Http\Controllers\HospitalController::class, 'save_lab_test_print_settings'])->name('save.lab_test.print_settings');
        Route::get('/copy-lab-investigation-signature', [App\Http\Controllers\HospitalController::class, 'copy_lab_test_signature'])->name('save.lab_test.copy_lab_test_signature');
        Route::post('/save-radiology-print-settings', [App\Http\Controllers\HospitalController::class, 'save_radiology_print_settings'])->name('save.radiology.print_settings');
        Route::post('/remove-radiology-print-setting-item', [App\Http\Controllers\HospitalController::class, 'remove_radiology_print_setting_item'])->name('remove.radiology.print_setting_item');
        Route::post('/save-radiology-print-settings', [App\Http\Controllers\HospitalController::class, 'save_radiology_print_settings'])->name('save.radiology.print_settings');
        Route::post('/remove-radiology-print-setting-item', [App\Http\Controllers\HospitalController::class, 'remove_radiology_print_setting_item'])->name('remove.radiology.print_setting_item');

        Route::get('/draft-settings', [App\Http\Controllers\HospitalController::class, 'draft_settings'])->name('draft_settings');

        // Unified Logs Routes
        Route::get('logs', [\App\Http\Controllers\LogViewController::class, 'logs'])->name('settings.logs');
        Route::get('sms-logs', function() {
            return redirect()->route('settings.logs');
        })->name('sms_logs');
        Route::get('user-activity-logs', function() {
            return redirect()->route('settings.logs', ['tab' => 'activity']);
        })->name('user_activity_logs');
        Route::get('logs/fetch-activity', [\App\Http\Controllers\LogViewController::class, 'fetch_activity_logs'])->name('settings.logs.fetch_activity');
        Route::get('logs/fetch-message', [\App\Http\Controllers\LogViewController::class, 'fetch_message_logs'])->name('settings.logs.fetch_message');
        Route::get('logs/fetch-abdm', [\App\Http\Controllers\LogViewController::class, 'fetch_abdm_logs'])->name('settings.logs.fetch_abdm');
        Route::get('logs/activity-details/{id}', [\App\Http\Controllers\LogViewController::class, 'activity_details'])->name('settings.logs.activity_details');
        Route::get('logs/message-details/{id}', [\App\Http\Controllers\LogViewController::class, 'message_details'])->name('settings.logs.message_details');
        Route::get('logs/abdm-details/{id}', [\App\Http\Controllers\LogViewController::class, 'abdm_details'])->name('settings.logs.abdm_details');

        //User

        Route::get('/hospital_add_staffmanager', [App\Http\Controllers\HospitalController::class, 'hospital_add_staffmanager'])->name('add.hospital_staffmanager');
        Route::post('/save_hospital_staffmanager', [App\Http\Controllers\HospitalController::class, 'save_hospital_staffmanager'])->name('save.hospital_staffmanager');
        Route::get('/staffchangeStatus',[App\Http\Controllers\HospitalController::class,'staff_status_update'])->name('staff.status_update');
        Route::post('/update_hospital_staffmanager', [App\Http\Controllers\HospitalController::class, 'update_hospital_staffmanager'])->name('update.hospital_staffmanager');
        Route::get('get_staff_data/{id}',[App\Http\Controllers\HospitalController::class,'get_staff_data'])->name('staff.get_staff_data');
        Route::get('edit_staff_role',[App\Http\Controllers\HospitalController::class,'edit_staff_role'])->name('staff.edit_staff_role');
        Route::get('update_staff_role',[App\Http\Controllers\HospitalController::class,'update_staff_role'])->name('staff.update_staff_role');
        Route::post('save-user-profile',[App\Http\Controllers\HospitalController::class,'save_user_profile'])->name('user.save_user_profile');
        Route::post('user-save-profile',[App\Http\Controllers\HospitalController::class,'save_profile'])->name('user.save_profile');

        //License Routes
        Route::get('/license', [App\Http\Controllers\HospitalController::class, 'manage_license'])->name('settings.license');
        Route::post('/fetch-license', [App\Http\Controllers\HospitalController::class, 'fetch_license'])->name('settings.fetch_license');
        Route::post('/update-empath-token', [App\Http\Controllers\HospitalController::class, 'update_empath_token'])->name('settings.update_empath_token');

        // Audit Log Backup & Restore Routes
        Route::get('/backup-restore', [App\Http\Controllers\AuditBackupController::class, 'index'])->name('settings.backup_restore');
        Route::get('/backup-restore/preview-time', [App\Http\Controllers\AuditBackupController::class, 'previewNextTime'])->name('settings.backup_restore.preview_time');
        Route::post('/backup-restore/save-settings', [App\Http\Controllers\AuditBackupController::class, 'saveSettings'])->name('settings.backup_restore.save_settings');
        Route::post('/backup-restore/create-backup', [App\Http\Controllers\AuditBackupController::class, 'createBackup'])->name('settings.backup_restore.create_backup');
        Route::get('/backup-restore/download/{id}', [App\Http\Controllers\AuditBackupController::class, 'downloadBackup'])->name('settings.backup_restore.download');
        Route::delete('/backup-restore/delete/{id}', [App\Http\Controllers\AuditBackupController::class, 'deleteBackup'])->name('settings.backup_restore.delete');
        Route::post('/backup-restore/restore/{id}', [App\Http\Controllers\AuditBackupController::class, 'restoreBackup'])->name('settings.backup_restore.restore');
        Route::post('/backup-restore/upload-restore', [App\Http\Controllers\AuditBackupController::class, 'uploadAndRestore'])->name('settings.backup_restore.upload_restore');

    });
});
Route::get('/draft_setting_status_update', [App\Http\Controllers\HospitalController::class, 'draft_setting_status_update'])->name('draft_setting_status_update');


// Common Routes that can be used in both SuperAdmin and Hospital Admin
Route::middleware(['auth'])->group(function () {

   // Also Giving routes to hospital admin for adding test/services
    Route::get('/all-service-list', [App\Http\Controllers\AdminController::class, 'all_service_list'])->name('service.all_list');
    Route::get('/mark_surgical_procedure', [App\Http\Controllers\AdminController::class,'mark_surgical_procedure'])->name('mark_surgical_procedure');
    Route::get('/service_list', [App\Http\Controllers\AdminController::class, 'service_list'])->name('service.list');
    Route::get('/service_group_list', [App\Http\Controllers\AdminController::class, 'service_group_list'])->name('service_group_list.list');
    Route::get('/add_service', [App\Http\Controllers\AdminController::class, 'add_service'])->name('add.service');
    Route::post('/save_service', [App\Http\Controllers\AdminController::class, 'save_service'])->name('save.service');
    Route::post('/save_service_sample', [App\Http\Controllers\AdminController::class, 'save_service_sample'])->name('save.service_sample');
    Route::post('/save_service_test_includes', [App\Http\Controllers\AdminController::class, 'save_service_test_includes'])->name('save.service_test_includes');
    Route::get('/edit_service/{id}', [App\Http\Controllers\AdminController::class, 'edit_service'])->name('edit.service');
    Route::get('/get_service_loinc_mapping_table', [App\Http\Controllers\AdminController::class, 'get_service_loinc_mapping_table'])->name('service.get_service_loinc_mapping_table');
    Route::post('/update_service/{id}', [App\Http\Controllers\AdminController::class, 'update_service'])->name('update.service');
    Route::get('/delete_service/{id}', [App\Http\Controllers\AdminController::class, 'delete_service'])->name('delete.service');
    Route::get('/changeServStatus',[App\Http\Controllers\AdminController::class,'service_status_update'])->name('service.status._update');
    Route::get('/changeServiceTag',[App\Http\Controllers\AdminController::class,'service_tag_update'])->name('service.tag_update');
    Route::get('/changeServiceSubFlagTag',[App\Http\Controllers\AdminController::class,'service_sub_flag_update'])->name('service.sub_flag_update');
    Route::get('/changeServiceUniversalCode',[App\Http\Controllers\AdminController::class,'service_universal_code_update'])->name('service.sub_universal_code_update');
    Route::post('/update_service_shortcode/{id}', [App\Http\Controllers\AdminController::class, 'update_service_short_code'])->name('update.service_short_code');
    Route::get('/services_included_in/{id}',[App\Http\Controllers\AdminController::class, 'services_included_in'])->name('service.includes');
    Route::get('/hospital_services/{id}', [App\Http\Controllers\AdminController::class, 'get_services'])->name('get.hospital_services');
    Route::post('/save_group_services/{id}', [App\Http\Controllers\AdminController::class, 'save_group_services'])->name('save.group_service');
    Route::get('/edit_service_attributes',[App\Http\Controllers\AdminController::class, 'edit_service_attributes'])->name('edit.service_attributes');
    Route::post('/save_service_attributes',[App\Http\Controllers\AdminController::class, 'save_service_attributes'])->name('save.service_attributes');
    Route::post('/update-service-input-type',[App\Http\Controllers\AdminController::class,'service_input_type_update'])->name('service.service_input_type_update');
    Route::get('/get-loinc-search',[App\Http\Controllers\AdminController::class,'get_loinc_search'])->name('service.get_loinc_search');
    Route::post('/update-loinc-code',[App\Http\Controllers\AdminController::class,'update_loinc_code'])->name('service.update_loinc_code');
    /* Route::post('/category/save',[App\Http\Controllers\AdminController::class,'save_category'])->name('category.save'); */
    Route::get('/updateCategoryTag',[App\Http\Controllers\AdminController::class,'update_category_tag'])->name('update.category');
    Route::get('edit-include-tests',[App\Http\Controllers\AdminController::class,'edit_include_tests'])->name('edit.include_tests');
    /* Route::get('/category/list', [App\Http\Controllers\AdminController::class, 'category_list'])->name('category.list');
     */

    Route::get('edit/labels',[\App\Http\Controllers\AdminController::class,'edit_lager_labels'])->name('edit.ledger.labels');
    Route::post('update/edit/labels',[\App\Http\Controllers\AdminController::class,'update_lager_labels'])->name('update.ledger.labels');

    // Lab Packages Routes
    Route::get('/lab_packages_list', [App\Http\Controllers\AdminController::class, 'lab_packages'])->name('superadmin.lab_packages.list');
    Route::post('/add_lab_papckage_list', [App\Http\Controllers\AdminController::class, 'add_lab_papckage'])->name('superadmin.add.lab_package');
    Route::post('/update_lab_papckage_list', [App\Http\Controllers\AdminController::class, 'update_lab_papckage'])->name('superadmin.update.lab_package');
    Route::get('/change_lab_package_status_list',[App\Http\Controllers\AdminController::class,'change_lab_package_status'])->name('superadmin.status_update.lab_package');

    Route::get('/lab_package_test_includes/{id}',[App\Http\Controllers\AdminController::class,'lab_package_test_includes'])->name('include_tests.lab_package');
    Route::post('/update_package_test_includes/{id}',[App\Http\Controllers\AdminController::class,'update_package_test_includes'])->name('update_include_tests.lab_package');

    /* Route::get('/category_test_includes/{id}',[App\Http\Controllers\AdminController::class,'category_test_includes'])->name('include_tests.category'); */
    Route::get('/update_category_test_includes_status',[App\Http\Controllers\AdminController::class,'update_status_category_test_includes'])->name('update_include_tests_status.category');
    Route::get('/category_test_includes_update/{id}',[App\Http\Controllers\AdminController::class,'category_test_includes'])->name('include_tests_update.category');

    Route::get('ipd/package_list', [App\Http\Controllers\AdminController::class, 'ipd_package_list'])->name('ipd.package_list');

    // Add new service
    Route::get('hospital/add-new-service', [App\Http\Controllers\AdminController::class, 'add_new_test'])->name('hospital.add_new_service');
    Route::post('hospital-service-save', [App\Http\Controllers\AdminController::class, 'create_service'])->name('create.service.save');
    Route::get('edit-test-details',[App\Http\Controllers\AdminController::class,'edit_test_details'])->name('edit.test_details');
    Route::get('list/edit-hospital-service',[App\Http\Controllers\AdminController::class,'edit_hospital_service'])->name('list.edit_hospital_service');

    // add lab variables
    Route::get('/labvariables',[App\Http\Controllers\AdminController::class, 'lab_variables'])->name('lab_variables');
    Route::post('/save_lab_variables',[App\Http\Controllers\AdminController::class, 'save_lab_variables'])->name('save.lab_variables');
    Route::get('/edit_lab_variables/{id}/{type}',[App\Http\Controllers\AdminController::class, 'edit_lab_variables'])->name('edit.lab_variables');
    Route::post('/update_lab_variables',[App\Http\Controllers\AdminController::class, 'update_lab_variables'])->name('update.lab_variables');
    Route::post('/validate_attributes_formula',[App\Http\Controllers\AdminController::class, 'validate_attributes_formula'])->name('validate.service_attributes_formula');
    // Route::post('/labvariables',[App\Http\Controllers\AdminController::class, 'lab_variables'])->name('lab_variables.save');

    // LIS Setup
    Route::get('/lis-setup',[App\Http\Controllers\HospitalController::class, 'lis_setup'])->name('lis_setup');
    Route::get('/lis-setup-form/{id?}',[App\Http\Controllers\HospitalController::class, 'lis_setup_form'])->name('lis_setup_form');
    Route::get('/lis-mapping-status-update',[App\Http\Controllers\HospitalController::class, 'lis_mapping_status_update'])->name('lis_mapping_status_update');
    Route::get('/get_group_included_tests',[App\Http\Controllers\HospitalController::class, 'get_group_included_tests'])->name('get_group_included_tests');
    Route::post('/save-lis-setup',[App\Http\Controllers\HospitalController::class, 'save_lis_setup'])->name('save_lis_setup');

    // Test Grouping and Sampling
    Route::get('/grouping-and-sampling',[App\Http\Controllers\HospitalController::class, 'group_and_sampling'])->name('group_and_sampling');

    // Outsource Labs Setup
    Route::get('/outsource-labs',[App\Http\Controllers\HospitalController::class, 'outsource_labs'])->name('outsource_labs');
    Route::get('/get-outsource-labs',[App\Http\Controllers\HospitalController::class, 'get_outsource_labs_form'])->name('get_outsource_labs_form');
    Route::post('/save-outsource-labs',[App\Http\Controllers\HospitalController::class, 'save_outsource_labs'])->name('save_outsource_labs');
    Route::get('/update-status-outsource-labs',[App\Http\Controllers\HospitalController::class, 'update_status_outsource_labs'])->name('update_status_outsource_labs');
    Route::get('/get-outsource-lab-charges',[App\Http\Controllers\HospitalController::class, 'get_outsource_lab_charges'])->name('get_outsource_lab_charges');
    Route::post('/save-outsource-lab-charges',[App\Http\Controllers\HospitalController::class, 'save_outsource_lab_charges'])->name('save_outsource_lab_charges');
    
    Route::get('/outsource-services',[App\Http\Controllers\HospitalController::class, 'outsource_services'])->name('outsource_services');
    Route::post('/update-outsource-service-lab',[App\Http\Controllers\HospitalController::class, 'update_outsource_service_lab'])->name('update_outsource_service_lab');
    Route::post('/auto-assign-min-outsource-labs',[App\Http\Controllers\HospitalController::class, 'auto_assign_min_outsource_labs'])->name('auto_assign_min_outsource_labs');
    Route::get('/get-outsource-lab-comparison',[App\Http\Controllers\HospitalController::class, 'get_outsource_lab_comparison'])->name('get_outsource_lab_comparison');

    // Master Outsource Services List & Lab Offerings
    Route::get('/master-outsource-services',[App\Http\Controllers\HospitalController::class, 'master_outsource_services'])->name('master_outsource_services');
    Route::get('/get-service-offering-labs',[App\Http\Controllers\HospitalController::class, 'get_service_offering_labs'])->name('get_service_offering_labs');
    Route::post('/save-service-offering-labs',[App\Http\Controllers\HospitalController::class, 'save_service_offering_labs'])->name('save_service_offering_labs');
    Route::post('/add-master-outsource-service',[App\Http\Controllers\HospitalController::class, 'add_master_outsource_service'])->name('add_master_outsource_service');
    Route::post('/remove-master-outsource-service',[App\Http\Controllers\HospitalController::class, 'remove_master_outsource_service'])->name('remove_master_outsource_service');
    Route::post('/set-service-default-lab',[App\Http\Controllers\HospitalController::class, 'set_service_default_lab'])->name('set_service_default_lab');


    // Doctor Routes
    Route::get('/doctor_list', [App\Http\Controllers\AdminController::class, 'doctor_list'])->name('doctor.list');
    Route::get('/add_doctor', [App\Http\Controllers\AdminController::class, 'add_doctor'])->name('add.doctor');
    Route::post('/save_doctor', [App\Http\Controllers\AdminController::class, 'save_doctor'])->name('save.doctor');
    Route::post('/update_doctor_login_pin', [App\Http\Controllers\AdminController::class, 'update_doctor_login_pin'])->name('doctor.update_doctor_login_pin');
    Route::post('/update_other_doctor_settings', [App\Http\Controllers\AdminController::class, 'update_other_doctor_settings'])->name('doctor.update_other_doctor_settings');
    Route::get('/edit_doct/{id}', [App\Http\Controllers\AdminController::class, 'edit_doct'])->name('edit.doc');
    Route::post('/update_doctor/{id}', [App\Http\Controllers\AdminController::class, 'update_doctor'])->name('update.doctor');
    Route::get('/delete_doct/{id}', [App\Http\Controllers\AdminController::class, 'delete_doct'])->name('delete.doct');
    Route::get('/changeStatus',[App\Http\Controllers\AdminController::class,'doctor_status_update'])->name('doctor.status_update');
    Route::get('/doctor_clinical_protocol/{id}', [App\Http\Controllers\AdminController::class, 'doctor_clinical_protocol'])->name('doc_clinical_protocol');
    Route::post('/save_doctor_clinical_protocol/{id}', [App\Http\Controllers\AdminController::class, 'save_doctor_clinical_protocol'])->name('save_doctor_clinical_protocol');

    Route::get('doctor-clinical-protocol-management-form',[App\Http\Controllers\AdminController::class, 'doctor_clinical_protocol_management_form'])->name('doctor.doctor_clinical_protocol_management_form');
    Route::post('doctor-clinical-protocol-remove',[App\Http\Controllers\AdminController::class, 'remove_doctor_clinical_protocol'])->name('doctor.remove_doctor_clinical_protocol');
    Route::post('doctor-clinical-protocol-save',[App\Http\Controllers\AdminController::class, 'save_doctor_clinical_protocol'])->name('doctor.save_doctor_clinical_protocol');

    Route::delete('doctor/delete-signature/{id}', [App\Http\Controllers\AdminController::class, 'deleteSignature'])->name('delete.doctorsignature');

    // Specialization Routes
    Route::get('/specialization_list', [App\Http\Controllers\AdminController::class, 'specialization_list'])->name('specialization.list');
    Route::get('/add_specialization', [App\Http\Controllers\AdminController::class, 'add_specialization'])->name('add.specialization');
    Route::post('/save_specialization', [App\Http\Controllers\AdminController::class, 'save_specialization'])->name('save.specialization');
    Route::get('/edit_specialization/{id}', [App\Http\Controllers\AdminController::class, 'edit_specialization'])->name('edit.specialization');
    Route::post('/update_specialization/{id}', [App\Http\Controllers\AdminController::class, 'update_specialization'])->name('update.specialization');
    Route::get('/delete_specialization/{id}', [App\Http\Controllers\AdminController::class, 'delete_specialization'])->name('delete.specialization');
    Route::get('/changeSpecStatus',[App\Http\Controllers\AdminController::class,'specialization_status_update'])->name('specialization.status_update');
    Route::post('/updateSpecializationNotes', [App\Http\Controllers\AdminController::class, 'update_specialization_notes'])->name('specialization.notes_update');

    // Complain Routes
    Route::get('/complain_list', [App\Http\Controllers\AdminController::class, 'symptom_list'])->name('complain.list');
    Route::post('/save_complain', [App\Http\Controllers\AdminController::class, 'save_symptom'])->name('save.symptom');
    Route::post('/update_complain', [App\Http\Controllers\AdminController::class, 'update_symptom'])->name('update.symptom');
    Route::get('/complainchangeStatus',[App\Http\Controllers\AdminController::class,'symptom_status_update'])->name('symptom.status_update');

    // Diagnosis Routes
    Route::get('/diagnosis_list', [App\Http\Controllers\AdminController::class, 'diagnosis_list'])->name('diagnosis.list');
    Route::post('/save_diagnosis', [App\Http\Controllers\AdminController::class, 'save_diagnosis'])->name('save.diagnosis');
    Route::post('/update_diagnosis', [App\Http\Controllers\AdminController::class, 'update_diagnosis'])->name('update.diagnosis');
    Route::get('/diagnosischangeStatus',[App\Http\Controllers\AdminController::class,'diagnosis_status_update'])->name('diagnosis.status_update');

    // Insurance Routes
    Route::get('/insurance_list', [App\Http\Controllers\AdminController::class, 'insurance_list'])->name('insurance.list');
    Route::get('/add_insurance', [App\Http\Controllers\AdminController::class, 'add_insurance'])->name('add.insurance');
    Route::post('/save_insurance', [App\Http\Controllers\AdminController::class, 'save_insurance'])->name('save.insurance');
    Route::get('/edit_insurance/{id}', [App\Http\Controllers\AdminController::class, 'edit_insurance'])->name('edit.insurance');
    Route::post('/update_insurance/{id}', [App\Http\Controllers\AdminController::class, 'update_insurance'])->name('update.insurance');
    Route::get('/delete_insurance/{id}', [App\Http\Controllers\AdminController::class, 'delete_insurance'])->name('delete.insurance');
    Route::get('/changeInsuStatus',[App\Http\Controllers\AdminController::class,'insurance_status_update'])->name('insurance._status_update');

    // Route::get('/tpa_list', [App\Http\Controllers\AdminController::class, 'tpa_list'])->name('tpa.list');

    // Insurance Routes
    Route::get('/tpa_list', [App\Http\Controllers\AdminController::class, 'tpa_list'])->name('tpa.list');
    Route::post('/save_tpa', [App\Http\Controllers\AdminController::class, 'save_tpa'])->name('save.tpa');
    Route::get('/changeTPAStatus',[App\Http\Controllers\AdminController::class,'tpa_status_update'])->name('tpa._status_update');

    // Room Type Routes
    Route::get('/room_type_list', [App\Http\Controllers\AdminController::class, 'room_type_list'])->name('roomtype.list');
    Route::post('/save_room_type', [App\Http\Controllers\AdminController::class, 'save_room_type'])->name('save.room_type');
    Route::post('/update_room_type', [App\Http\Controllers\AdminController::class, 'update_room_type'])->name('update.room_type');
    Route::get('/roomtypechangeStatus',[App\Http\Controllers\AdminController::class,'roomtype_status_update'])->name('roomtype.status_update');
    Route::get('/roomtypechangeBedTransferStatus',[App\Http\Controllers\AdminController::class,'roomtype_bed_transfer_status_update'])->name('roomtype.bed_transfer_status_update');
    Route::get('/roomtypechangeOtBookingStatus',[App\Http\Controllers\AdminController::class,'roomtype_ot_booking_status_update'])->name('roomtype.ot_booking_status_update');

    // Miscellaneous Charges Routes
    Route::get('/miscellaneous_charges_list', [App\Http\Controllers\AdminController::class, 'miscellaneous_charges_list'])->name('miscellaneous_charges.list');
    Route::post('/save_miscellaneous_charges', [App\Http\Controllers\AdminController::class, 'save_miscellaneous_charges'])->name('save.miscellaneous_charges');
    Route::get('/edit_miscellaneous_charges/{id}', [App\Http\Controllers\AdminController::class, 'edit_miscellaneous_charges'])->name('edit.miscellaneous_charges');
    Route::post('/update_miscellaneous_charges/{id}', [App\Http\Controllers\AdminController::class, 'update_miscellaneous_charges'])->name('update.miscellaneous_charges');
    Route::get('/changemiscStatus',[App\Http\Controllers\AdminController::class,'procedure_status_update'])->name('miscellaneous_charges.status_update');

    // Procedures Routes
    Route::get('/procedures_list', [App\Http\Controllers\AdminController::class, 'procedures_list'])->name('procedures.list');
    Route::post('/save_procedures', [App\Http\Controllers\AdminController::class, 'save_procedures'])->name('save.procedures');
    Route::get('/edit_procedures/{id}', [App\Http\Controllers\AdminController::class, 'edit_procedures'])->name('edit.procedures');
    Route::post('/update_procedures/{id}', [App\Http\Controllers\AdminController::class, 'update_procedures'])->name('update.procedures');
    Route::get('/changeProcedureStatus',[App\Http\Controllers\AdminController::class,'procedure_status_update'])->name('procedures.status_update');


    //  Package Routes
    Route::get('/package_list', [App\Http\Controllers\AdminController::class, 'package_list'])->name('package.list');
    Route::post('/save_package', [App\Http\Controllers\AdminController::class, 'save_package'])->name('save.package');
    Route::post('/update_package', [App\Http\Controllers\AdminController::class, 'update_package'])->name('update.package');
    Route::get('/packagechangeStatus',[App\Http\Controllers\AdminController::class,'package_status_update'])->name('package.status._update');

    // Discount Routes
    Route::get('/discount_list', [App\Http\Controllers\AdminController::class, 'discount_list'])->name('discount.list');
    Route::post('/save_discount', [App\Http\Controllers\AdminController::class, 'save_discount'])->name('save.discount');
    Route::get('/edit_discount/{id}', [App\Http\Controllers\AdminController::class, 'edit_discount'])->name('edit.discount');
    Route::post('/update_discount/{id}', [App\Http\Controllers\AdminController::class, 'update_discount'])->name('update.discount');
    Route::get('/changediscStatus',[App\Http\Controllers\AdminController::class,'discount_status_update'])->name('discount.status_update');

    Route::get('/vaccine_list', [App\Http\Controllers\AdminController::class, 'vaccine_list'])->name('vaccine.list');
    Route::post('/save_vaccine', [App\Http\Controllers\AdminController::class, 'vaccine_schedule'])->name('save.vaccine_schedule');




    // REPORTS ROUTES
    Route::get('/reports/visit-report-fetch',[\App\Http\Controllers\ReportController::class,'opd_visit_report_fetch'])->name('reports.opd.visit_fetch');
    Route::get('/reports/revenue-report-fetch',[\App\Http\Controllers\ReportController::class,'payment_report_fetch'])->name('reports.opd.revenue_fetch');
    Route::get('/reports/bill-report-fetch',[\App\Http\Controllers\ReportController::class,'opd_revenue_bill_fetch'])->name('reports.opd.bill_fetch');
    Route::get('/reports/revenue-single-bill-fetch',[\App\Http\Controllers\ReportController::class,'opd_revenue_fetch_single_bill'])->name('reports.opd.revenue_fetch_single_bill');
    Route::get('/reports/investigation-report-fetch',[\App\Http\Controllers\ReportController::class,'opd_investigation_report_fetch'])->name('reports.opd.investigation_fetch');
    Route::get('/reports/doctor-report-fetch',[\App\Http\Controllers\ReportController::class,'opd_doctor_report_fetch'])->name('reports.opd.doctor_fetch');
    Route::get('/reports/doctor-report-fetch-new-vs-followup',[\App\Http\Controllers\ReportController::class,'fetch_doctor_report'])->name('reports.opd.fetch_doctor_report');
    Route::get('/reports/patient-registration-fetch',[\App\Http\Controllers\ReportController::class,'patient_registration_fetch'])->name('reports.opd.patient_registration_fetch');
    Route::get('/reports/opd-referral-wise-report',[\App\Http\Controllers\ReportController::class,'opd_referral_wise_report'])->name('reports.opd.referral_wise_report');
    Route::get('/reports/registration-report-fetch',[\App\Http\Controllers\ReportController::class,'registration_report_fetch'])->name('reports.registration_fetch');
    Route::get('/reports/admission-report-fetch',[\App\Http\Controllers\ReportController::class,'admission_report_fetch'])->name('reports.admission_fetch');

    Route::get('/reports/all-bill-fetch',[\App\Http\Controllers\ReportController::class,'all_revenue_bill_fetch'])->name('reports.all.bill_fetch');
    Route::get('/reports/all-bill-fetch-with-gst',[\App\Http\Controllers\ReportController::class,'all_revenue_bill_fetch_with_gst'])->name('reports.all.bill_fetch_with_gst');
    Route::get('/reports/all-bill-fetch-with-tds',[\App\Http\Controllers\ReportController::class,'all_revenue_bill_fetch_with_tds'])->name('reports.all.bill_fetch_with_tds');
    Route::get('/reports/ipd-report-fetch',[\App\Http\Controllers\ReportController::class,'ipd_revenue_bill_fetch'])->name('reports.ipd.bill_fetch');
    Route::get('/reports/ipd-payment-report-fetch',[\App\Http\Controllers\ReportController::class,'payment_report_fetch'])->name('reports.ipd.payment_fetch');
    Route::get('/reports/opd-report-fetch',[\App\Http\Controllers\ReportController::class,'opd_report_fetch'])->name('reports.opd.opd_report_fetch');
    Route::get('/reports/opd-report-fetch2',[\App\Http\Controllers\ReportController::class,'opd_report_fetch2'])->name('reports.opd.opd_report_fetch2');
    Route::get('/reports/er-report', [\App\Http\Controllers\ReportController::class, 'er_report'])->name('reports.er');
    Route::get('/reports/er-report-fetch', [\App\Http\Controllers\ReportController::class, 'er_report_fetch'])->name('reports.er.er_report_fetch');


    Route::get('/reports/month-revenue-and-collection',[\App\Http\Controllers\ReportController::class,'month_revenue_and_collection'])->name('reports.month_revenue_and_collection');

    Route::get('/reports/ipd-room-report-fetch',[\App\Http\Controllers\ReportController::class,'ipd_room_report_fetch'])->name('reports.ipd.room');
    Route::get('/reports/ipd-ot-report-fetch',[\App\Http\Controllers\ReportController::class,'ipd_ot_report_fetch'])->name('reports.ipd.ot');

    Route::get('/reports/consultation-report-fetch',[\App\Http\Controllers\ReportController::class,'opd_consultation_report_fetch'])->name('reports.opd.consultation_report_fetch');
    Route::get('/reports/consultation-report-fetch2',[\App\Http\Controllers\ReportController::class,'servicewise_report_fetch'])->name('reports.consultation_fetch');

    Route::get('/reports/ipd-investigation-report-fetch',[\App\Http\Controllers\ReportController::class,'servicewise_report_fetch'])->name('reports.ipd.investigation_fetch');
    Route::get('reports/cancelled-services',[\App\Http\Controllers\ReportController::class,'cancelled_services'])->name('reports.cancelled_services');

    //OPD CONSOLIDATED REPORTS
    Route::get('/reports/consolidated-report',[\App\Http\Controllers\ReportController::class,'consolidated_report'])->name('reports.consolidated');
    Route::get('/reports/consolidated-report-v2',[\App\Http\Controllers\ReportController::class,'consolidated_report_v2'])->name('reports.consolidated_v2');
    Route::get('/reports/consolidated-test-grouped-report',[\App\Http\Controllers\ReportController::class,'consolidated_grouped_report'])->name('reports.consolidated_grouped_report');
    Route::get('/reports/consolidated-test-grouped-detailed-report',[\App\Http\Controllers\ReportController::class,'consolidated_grouped_detailed_report'])->name('reports.consolidated_grouped_detailed_report');


    //Admin Quick Report
    Route::get('/reports/admin-reports',[\App\Http\Controllers\ReportController::class,'admin_reports'])->name('reports.opd.admin_reports');
    Route::get('/reports/admin-reports-v2',[\App\Http\Controllers\ReportController::class,'admin_reports_v2'])->name('reports.opd.admin_reports.v2');

    Route::get('/reports/toggle_from_favorite_page_link',[\App\Http\Controllers\ReportController::class,'toggle_from_favorite_page_link'])->name('toggle_from_favorite_page_link');

    Route::get('/reports/consolidated-outpatient-visit-wise-investigation',[\App\Http\Controllers\ReportController::class,'visit_wise_consolidated_investigation'])->name('reports.visit_wise_consolidated_investigation');
    Route::get('/fetch-report/consolidated-outpatient-visit-wise-investigation',[\App\Http\Controllers\ReportController::class,'fetch_visit_wise_consolidated_investigation'])->name('fetch.visit_wise_consolidated_investigation');

    Route::get('/reports/consolidated-outpatient-visit-wise-revenue',[\App\Http\Controllers\ReportController::class,'visit_wise_consolidated_revenue'])->name('reports.visit_wise_consolidated_revenue');
    Route::get('/fetch-report/consolidated-outpatient-visit-wise-revenue',[\App\Http\Controllers\ReportController::class,'fetch_visit_wise_consolidated_revenue'])->name('fetch.visit_wise_consolidated_revenue');

    Route::get('/reports/ipd-patient-admission-report',[\App\Http\Controllers\ReportController::class,'inpatient_admission_report'])->name('reports.inpatient_admission_report');
    Route::get('/reports/ipd-patient-admission-report-v2',[\App\Http\Controllers\ReportController::class,'inpatient_admission_report_v2'])->name('reports.inpatient_admission_report_v2');
    Route::get('/reports/ipd-patient-report',[\App\Http\Controllers\ReportController::class,'fetch_inpatient_report'])->name('fetch.fetch_inpatient_report');
    Route::get('/fetch-report/inpatient-admission-report',[\App\Http\Controllers\ReportController::class,'fetch_inpatient_admission_report'])->name('fetch.inpatient_admission_report');
    Route::get('/fetch-report/inpatient-discharge-report',[\App\Http\Controllers\ReportController::class,'fetch_inpatient_discharge_report'])->name('fetch.inpatient_discharge_report');
    Route::get('/fetch-report/active-inpatient-report',[\App\Http\Controllers\ReportController::class,'fetch_active_inpatient_report'])->name('fetch.active_inpatient_report');

    Route::get('/reports/ipd-patient-discharge-report-payment-wise',[\App\Http\Controllers\ReportController::class,'inpatient_discharge_report_payment_wise'])->name('reports.inpatient_discharge_report_payment_wise');
    // Route::get('/reports/ipd-patient-report',[\App\Http\Controllers\ReportController::class,'fetch_inpatient_report'])->name('fetch.fetch_inpatient_report');

    Route::get('/reports/collection-payment-report-fetch',[\App\Http\Controllers\ReportController::class,'payment_report_fetch'])->name('reports.payment_fetch');


    Route::get('/reports/revenue-report-fetch2',[\App\Http\Controllers\ReportController::class,'opd_revenue_report_fetch2'])->name('reports.opd.revenue_fetch2');
    Route::get('/reports/opd-consolidated-report-fetch',[\App\Http\Controllers\ReportController::class,'opd_consolidated_report_fetch'])->name('reports.opd.consolidated_fetch');
    Route::get('/reports/ipd-consolidated-report-fetch',[\App\Http\Controllers\ReportController::class,'ipd_consolidated_report_fetch'])->name('reports.ipd.consolidated_fetch');

    Route::get('/reports/revenue-daily-report-fetch',[\App\Http\Controllers\ReportController::class,'opd_revenue_daily_report_fetch'])->name('reports.opd.revenue_daily_fetch');

    //Reports Helper Routes
    Route::get('/get-doctors-by-spec', [\App\Http\Controllers\ReportController::class,'get_doctor_by_spec'])->name('report.get_doctor_by_spec');
    Route::get('/get-investigation-by-spec', [\App\Http\Controllers\ReportController::class,'get_investigation_by_spec'])->name('report.get_investigation_by_spec');


    Route::get('/aggregate_report/leads', [\App\Http\Controllers\ReportController::class,'aggregate_leads'])->name('report.aggregate_leads');
    Route::get('/aggregate_report/referals', [\App\Http\Controllers\ReportController::class,'aggregate_referals'])->name('report.aggregate_referals');

    Route::get('/reports/opd-user-wise-report',[\App\Http\Controllers\ReportController::class,'opd_user_wise_report'])->name('reports.opd.user_wise');


    Route::get('/edit_log_reports', [App\Http\Controllers\ReportController::class, 'edit_log_report'])->name('admin.edit_log_reports');
    Route::get('record/edit_log_reports', [App\Http\Controllers\ReportController::class, 'edit_log_report_filter'])->name('reports.editlog');
    ########################################### IPD REports ###########################################

    Route::get('/reports/ipd-revenue-report-fetch',[\App\Http\Controllers\ReportController::class,'ipd_revenue_report_fetch'])->name('reports.ipd.revenue_fetch');
    Route::get('/reports/ipd-revenue-report-fetch2',[\App\Http\Controllers\ReportController::class,'ipd_revenue_report_fetch2'])->name('reports.ipd.revenue_fetch2');
    Route::get('/reports/ipd-discharge-report-fetch',[\App\Http\Controllers\ReportController::class,'ipd_discharge_report_fetch'])->name('reports.ipd.discharge');
    Route::get('/reports/ipd-package-report-fetch',[\App\Http\Controllers\ReportController::class,'ipd_package_report_fetch'])->name('reports.ipd.package');
    Route::get('/reports/ipd-package-all-details', [\App\Http\Controllers\ReportController::class, 'ipd_package_all_details'])->name('reports.ipd.package.all_details');

    Route::get('/reports/patient-overall-report', [\App\Http\Controllers\ReportController::class,'patient_overall_report'])->name('reports.patient_overall');

    Route::get('/admin/billing-analytics', [\App\Http\Controllers\ReportController::class, 'billingAnalytics'])->name('admin.billing.analytics');
    Route::get('admin/labtest-analytics', [\App\Http\Controllers\ReportController::class, 'labTestAnalytics'])->name('admin.labtest.analytics');

    // Patient OverView
    Route::get('report/charts/{chart_type}',[\App\Http\Controllers\ReportController::class, 'generate_chart'])->name('generate_chart');
    Route::get('report/charts/patients-registration', [\App\Http\Controllers\ReportController::class, 'patientRegistrationChartData'])->name('chart_report.patient-registration');
    Route::get('report/charts/patients-visits', [\App\Http\Controllers\ReportController::class, 'patientVisitChartData'])->name('chart_report.patient-visits');
    Route::get('report/charts/patients-admitted', [\App\Http\Controllers\ReportController::class, 'patientAdmittedChartData'])->name('chart_report.patient-admitted');
    Route::get('report/charts/new-patients-admitted', [\App\Http\Controllers\ReportController::class, 'patientNewAdmittedChartData'])->name('chart_report.new-patient-admitted');
    Route::get('report/charts/patients-discharged', [\App\Http\Controllers\ReportController::class, 'patientDischargedChartData'])->name('chart_report.patient-discharged');
    Route::get('report/charts/billing', [\App\Http\Controllers\ReportController::class, 'billingChartData'])->name('chart_report.billings');
    Route::get('report/charts/service-wise-revenue', [\App\Http\Controllers\ReportController::class, 'serviceRevenueChartData'])->name('chart_report.service-wise-revenue');
    Route::get('report/charts/department-wise-revenue', [\App\Http\Controllers\ReportController::class, 'departmentRevenueChartData'])->name('chart_report.department-wise-revenue');
    Route::get('report/charts/opd-revenue', [\App\Http\Controllers\ReportController::class, 'opdRevenueChartData'])->name('chart_report.opd-revenue');
    Route::get('report/charts/ipd-revenue', [\App\Http\Controllers\ReportController::class, 'ipdRevenueChartData'])->name('chart_report.ipd-revenue');
    Route::get('report/charts/lab-test-booked', [\App\Http\Controllers\ReportController::class, 'labTestBookedChartData'])->name('chart_report.lab-test-booked');
    Route::get('report/doctor-overall', [\App\Http\Controllers\ReportController::class, 'doctorOverallData'])->name('reports.doctor_overall');
    Route::get('report/department-overall', [\App\Http\Controllers\ReportController::class, 'departmentOverallData'])->name('reports.department_overall');
    Route::get('report/charts/lab-opd-revenue', [\App\Http\Controllers\ReportController::class, 'labOpdRevenueChartData'])->name('chart_report.lab-opd-revenue');
    Route::get('report/charts/lab-ipd-revenue', [\App\Http\Controllers\ReportController::class, 'labIpdRevenueChartData'])->name('chart_report.lab-ipd-revenue');

    Route::get('reports/patient-overview/{section?}', [\App\Http\Controllers\ReportController::class, 'patientReportOverview'])->name('reports.patient.overview');
    Route::get('reports/new-patient-overview', [\App\Http\Controllers\ReportController::class, 'newPatientReportOverview'])->name('reports.newpatient.overview');
    Route::get('reports/opdvisit-overview', [\App\Http\Controllers\ReportController::class, 'opdVisitOverview'])->name('reports.opdvisit.overview');
    Route::get('reports/new-admission-overview', [\App\Http\Controllers\ReportController::class, 'newAdmissionOverview'])->name('reports.newAdmission.overview');
    Route::get('reports/current-admission-overview', [\App\Http\Controllers\ReportController::class, 'currentAdmissionOverview'])->name('reports.currentAdmission.overview');
    Route::get('reports/patient-registration-list', [\App\Http\Controllers\ReportController::class, 'patientRegistrationList'])->name('report.patient-registration-list');
    Route::get('reports/visits', [\App\Http\Controllers\ReportController::class, 'patientVisitReports'])->name('reports.visits');

    Route::get('reports/visit-overview', [\App\Http\Controllers\ReportController::class, 'visitOverview'])->name('reports.visits.overview');

    // new reports Routes #########################################################################################

    Route::get('reports/overall-dashboard', [\App\Http\Controllers\ReportController::class, 'overallDashboard'])->name('reports.overall.dashboard');
    // insurace reports
    // Route::get('reports/new-patient-registrations/{type?}/{year?}/{month?}', [\App\Http\Controllers\ReportController::class, 'newPatientRegistrations'])->name('reports.newpatient.registrations');
    Route::get('reports/insurance/new-registration',[\App\Http\Controllers\ReportController::class, 'newRegistrationPatientWiseReport'])->name('reports.opd.new_registrations');
    Route::get('reports/visit-wise',[\App\Http\Controllers\ReportController::class, 'opdVisitWiseReport'])->name('reports.opd.visits');
    Route::get('reports/new-admission-wise',[\App\Http\Controllers\ReportController::class, 'newAdmissionWiseReport'])->name('reports.ipd.new_admissions');
    Route::get('reports/ipd-admission-wise',[\App\Http\Controllers\ReportController::class, 'ipdAdmissionWiseReport'])->name('reports.ipd.admissions');
    Route::get('reports/discharge-wise',[\App\Http\Controllers\ReportController::class, 'ipdDischargeWiseReport'])->name('reports.ipd.discharged');
    Route::get('reports/bed-occupancy-wise',[\App\Http\Controllers\ReportController::class, 'bed_occupancyWiseReport'])->name('reports.ipd.bed_occupancy');
    Route::get('reports/mortality-wise',[\App\Http\Controllers\ReportController::class, 'mortality_wise_report'])->name('reports.ipd.mortality');

    Route::get('reports/doctor-overview', [\App\Http\Controllers\ReportController::class, 'doctorOverview'])->name('reports.doctor.overview');
    Route::get('reports/doctor-opd-visits', [\App\Http\Controllers\ReportController::class, 'doctorOPDVisitReport'])->name('reports.doctor_opd_visits.overview');
    Route::get('reports/doctor-ipd-admissions', [\App\Http\Controllers\ReportController::class, 'doctorIPDAdmissionReport'])->name('reports.doctor_ipd_admissions.overview');
    Route::get('reports/doctor-ipd-discharges', [\App\Http\Controllers\ReportController::class, 'doctorIPDDischargesReport'])->name('reports.doctor_ipd_discharges.overview');

    Route::get('reports/department-overview', [\App\Http\Controllers\ReportController::class, 'DepartmentOverview'])->name('reports.department.overview');
    Route::get('reports/department-wise-opd-visits', [\App\Http\Controllers\ReportController::class, 'departmentOPDVisitReport'])->name('reports.department_opd_visits.overview');
    Route::get('reports/department-wise-ipd-admissions', [\App\Http\Controllers\ReportController::class, 'departmentIPDAdmissionReport'])->name('reports.department_ipd_admissions.overview');
    Route::get('reports/department-wise-ipd-discharges', [\App\Http\Controllers\ReportController::class, 'departmentIPDDischargesReport'])->name('reports.department_ipd_discharges.overview');

    Route::get('reports/department-summary', [\App\Http\Controllers\ReportController::class, 'DepartmentSummary'])->name('reports.department.summary');
    Route::get('/fetch-report/department-summary',[\App\Http\Controllers\ReportController::class,'fetch_department_summary'])->name('fetch.department_summary');
    Route::get('reports/department-details-summary',[\App\Http\Controllers\ReportController::class,'fetch_department_details_summary'])->name('department.report.details');

    Route::get('reports/hospital-overview', [\App\Http\Controllers\ReportController::class, 'hospitalOverview'])->name('reports.hospital.overview');
    Route::get('reports/hospital-overview/tab/{tab}', [\App\Http\Controllers\ReportController::class, 'getHospitalOverviewTab'])->name('reports.hospital.overview.tab');

    Route::get('reports/financial-overview', [\App\Http\Controllers\ReportController::class, 'financialOverview'])->name('reports.financial.overview');
    Route::get('reports/financial-opd-revenue', [\App\Http\Controllers\ReportController::class, 'financialOPDVisitReport'])->name('reports.opd_revenue.overview');
    Route::get('reports/financial-ipd-revenue', [\App\Http\Controllers\ReportController::class, 'financialIPDVisitReport'])->name('reports.ipd_revenue.overview');

    Route::get('reports/opd-refund-overview', [\App\Http\Controllers\ReportController::class, 'opdRefundOverview'])->name('reports.opd_refund.overview');
    Route::get('reports/opd-discount-overview', [\App\Http\Controllers\ReportController::class, 'opdDiscountOverview'])->name('reports.opd_discount.overview');

    Route::get('reports/ipd-refund-overview', [\App\Http\Controllers\ReportController::class, 'ipdRefundOverview'])->name('reports.ipd_refund.overview');
    Route::get('reports/ipd-discount-overview', [\App\Http\Controllers\ReportController::class, 'ipdDiscountOverview'])->name('reports.ipd_discount.overview');

    Route::get('reports/service-wise-opd-revenue', [\App\Http\Controllers\ReportController::class, 'serviceWiseOPDVisitReport'])->name('reports.service_wise_opd_revenue.overview');
    Route::get('reports/service-wise-ipd-revenue', [\App\Http\Controllers\ReportController::class, 'serviceWiseIPDVisitReport'])->name('reports.service_wise_ipd_revenue.overview');

    Route::get('reports/services-overview', [\App\Http\Controllers\ReportController::class, 'servicesOverview'])->name('reports.services.overview');
    Route::get('reports/opd-services-overview', [\App\Http\Controllers\ReportController::class, 'opdServicesOverview'])->name('reports.opd_services.overview');
    Route::get('reports/ipd-services-overview', [\App\Http\Controllers\ReportController::class, 'ipdServicesOverview'])->name('reports.ipd_services.overview');

    Route::get('reports/lab-overview', [\App\Http\Controllers\ReportController::class, 'labOverview'])->name('reports.lab.overview');

    Route::get('reports/lab-opd', [\App\Http\Controllers\ReportController::class, 'labOPDReport'])->name('reports.lab_opd.overview');
    Route::get('reports/lab-ipd', [\App\Http\Controllers\ReportController::class, 'labIPDReport'])->name('reports.lab_ipd.overview');

    // Route::get('reports/pharmacy-overview', [\App\Http\Controllers\ReportController::class, 'PharmacyOverview'])->name('reports.pharmacy.overview');
    Route::get('reports/financial-pharmacy-revenue', [\App\Http\Controllers\ReportController::class, 'financialPharmacyReport'])->name('reports.pharmacy_revenue.overview');

    Route::get('reports/pharmacy', [\App\Http\Controllers\ReportController::class, 'pharmacyReport'])->name('reports.pharmacy.overview');
    Route::get('reports/pharmacy-sellbook', [\App\Http\Controllers\ReportController::class, 'pharmacySellBook'])->name('pharmacy.sellbook');

    Route::get('reports/pharmacy-sellbook2', [\App\Http\Controllers\ReportController::class, 'pharmacySellBook2'])->name('pharmacy.sellbook2');
    Route::get('reports/pharmacy-sellbook-simple', [\App\Http\Controllers\ReportController::class, 'pharmacySellBookSimple'])->name('pharmacy.sellbook.simple');
    Route::get('reports/pharmacy-sell-return', [\App\Http\Controllers\ReportController::class, 'pharmacySellReturn'])->name('pharmacy.sellReturn');
    Route::get('reports/pharmacy-bill-reconciliation', [\App\Http\Controllers\ReportController::class, 'pharmacyBillReconciliation'])->name('reports.pharmacy.bill_reconciliation');
    Route::get('salebook-details',[\App\Http\Controllers\ReportController::class, 'salebook_details'])->name('salebook.details');
    Route::get('item-wise-sale-details',[\App\Http\Controllers\ReportController::class, 'itemwise_sale_details'])->name('itemwise.sale.details');
    Route::get('item-wise-sale-profitability',[\App\Http\Controllers\ReportController::class, 'itemwise_sale_profitability'])->name('itemwise.sale.profetibility');

    Route::get('reports/pharmacy-day-sale', [\App\Http\Controllers\ReportController::class, 'pharmacyDaySale'])->name('reports.pharmacy.day_sale');
    Route::get('reports/pharmacy-daily-sale', [\App\Http\Controllers\ReportController::class, 'pharmacyDailySale'])->name('reports.pharmacy.daily_sale');
    Route::get('reports/pharmacy-day-wise-sale/{from_date}/{to_date}/{store}', [\App\Http\Controllers\ReportController::class, 'pharmacyDayWiseSaleReport'])->name('reports.pharmacy.day_wise_sale');
    Route::get('reports/bill_view/{id}', [\App\Http\Controllers\ReportController::class, 'billView'])->name('bill.view');
    Route::get('reports/pharmacy-purchasebook', [\App\Http\Controllers\ReportController::class, 'pharmacyPurchaseBook'])->name('pharmacy.purchasebook');
    Route::get('reports/tpa-sharing', [\App\Http\Controllers\ReportController::class, 'tpaSharing'])->name('pharmacy.tpaSharing');
    Route::get('reports/doctor-pharmacy-sale', [\App\Http\Controllers\ReportController::class, 'doctorSale'])->name('pharmacy.doctorSale');
    Route::get('reports/doctor-pharmacy-sale-details', [\App\Http\Controllers\ReportController::class, 'doctorSaleDetails'])->name('pharmacy.doctor_wise_sale_details');
    Route::get('reports/pharmacy-item-sale', [\App\Http\Controllers\ReportController::class, 'itemSale'])->name('pharmacy.itemSale');
    Route::get('reports/pharmacy-item-sale-details', [\App\Http\Controllers\ReportController::class, 'itemSaleDetails'])->name('pharmacy.item_wise_sale_details');

    Route::get('reports/gstr-report', [\App\Http\Controllers\ReportController::class, 'gstReport'])->name('gst.report');
    Route::get('reports/gstr1-report', [\App\Http\Controllers\ReportController::class, 'gstR1'])->name('reports.pharmacy.gstr1');
    Route::get('reports/gst3b-report', [\App\Http\Controllers\ReportController::class, 'gst3b'])->name('reports.pharmacy.gst3b');
    Route::get('reports/gst3b-details', [\App\Http\Controllers\ReportController::class, 'gst3bDetails'])->name('reports.pharmacy.gst3b_details');

    //GSTR2B RECONCILATION
    Route::get('reports/gstr2b-reconcilation', [\App\Http\Controllers\ReportController::class, 'gstR2B_reconcilation'])->name('reports.gstR2B_reconcilation');
    Route::post('reports/gstr2b-save-uploads', [\App\Http\Controllers\ReportController::class, 'gstR2B_save_upload'])->name('reports.gstR2B_save_upload');
    Route::get('reports/gstr2b-fetch-reconcile-data', [\App\Http\Controllers\ReportController::class, 'gstR2B_fetch_reconcile_data'])->name('reports.gstR2B_fetch_reconcile_data');

    // Payment History
     Route::get('reports/payment-history', [\App\Http\Controllers\ReportController::class, 'payment_history_report'])->name('pharmacy.paymentHistoryReport');

    //GST REPORTS EXPORT
    Route::get('reports/gstr-1-sale-export', [\App\Http\Controllers\ReportController::class, 'pharmacySaleGSTExport'])->name('pharmacySaleGSTExport');
    Route::get('reports/gst-2-purchase-export', [\App\Http\Controllers\ReportController::class, 'pharmacyPurchaseBookExport'])->name('pharmacyPurchaseBookExport');

    //HR REPORTS

    Route::get('reports/hr-reports', [\App\Http\Controllers\ReportController::class, 'salary_fetch'])->name('reports.hr.salary_fetch');
    Route::get('reports/hr-reports-v2', [\App\Http\Controllers\ReportController::class, 'salary_fetch_v2'])->name('reports.hr.salary_fetch.v2');
    Route::get('reports/hr-reports-credit', [\App\Http\Controllers\ReportController::class, 'salary_credit_fetch'])->name('reports.hr.salary_credit_fetch');
    Route::get('reports/pf-text-download/{month}/{year}', [\App\Http\Controllers\ReportController::class, 'pf_download'])->name('pf.txt.download');
    Route::get('reports/hr-pf-txt', [\App\Http\Controllers\ReportController::class, 'salary_pf_txt_report'])->name('reports.hr.pf_txt');
    Route::get('reports/hr-esi-export/{month}/{year}',[\App\Http\Controllers\ReportController::class, 'download_esi_csv'])->name('hr.esi.export');
    Route::get('reports/hr-salary-esi',[\App\Http\Controllers\ReportController::class, 'salary_esi'])->name('reports.hr.salary_esi');
    Route::post('reports/hr-esi-update',[\App\Http\Controllers\ReportController::class, 'salary_esi_update'])->name('salary.esi.update.single');
    // Whatsapp Report
    Route::get('reports/whatsapp-reports', [\App\Http\Controllers\ReportController::class, 'whatsapp_report'])->name('reports.whatsapp');



    // Culture Report Setup
    Route::get('culture/setup',[App\Http\Controllers\CultureReportController::class,'cultureGroup'])->name('lab.culture.report');
    Route::post("culture/store",[App\Http\Controllers\CultureReportController::class,'insertCultureGroup'])->name('culture.store');
    Route::post("culture/fungi/store",[App\Http\Controllers\CultureReportController::class,'insertCultureFungi'])->name('culture.fungi.store');
    Route::post("culture/antibiotic/store",[App\Http\Controllers\CultureReportController::class,'insertCultureAntibiotic'])->name('culture.antibiotic.store');

    Route::post("culture/test_organisam/store",[App\Http\Controllers\CultureReportController::class,'insertTestOrganism'])->name('culture.test_organisam.store');

    Route::post('/update-antibiotic-status', [App\Http\Controllers\CultureReportController::class,'updateAntibioticStatus'])->name('update.antibiotic.status');

    Route::get('test.specimen.edit/{id}', [App\Http\Controllers\CultureReportController::class,'editTestSpecimen'])->name('test.specimen.edit');

    Route::post('/update-frequently-drugs', [App\http\Controllers\CultureReportController::class, 'updateFrequentlyUsedDrug'])->name('frequently.used.drug');

    Route::post('culture/organism/add', [App\http\Controllers\CultureReportController::class, 'addOrgnismDrug'])->name('add.organism.drug');

    // Organism Drug Mapping
    Route::post('culture/orgnsm/drug/map', [App\http\Controllers\CultureReportController::class, 'organismDrugMapping'])->name('organism_drug_mapping');
    Route::get('culture/frequently/drug/{id}', [App\http\Controllers\CultureReportController::class, 'organismDrug'])->name('culture.frequently.drug');

    Route::get('include/organism/drugs/{id}', [App\http\Controllers\CultureReportController::class, 'includeOrganismDrugs'])->name('include_organism_drugs');

    Route::post('/organism/edit', [App\http\Controllers\CultureReportController::class, 'editOrganism'])
     ->name('organism.get');
    Route::post('/organism/update', [App\http\Controllers\CultureReportController::class, 'updateOrganism'])
    ->name('organism.update');
    Route::get('/organism/active-list', [App\http\Controllers\CultureReportController::class, 'activeList'])
     ->name('organism.activeList');
    Route::post('/organism/toggle-active', [App\http\Controllers\CultureReportController::class, 'toggleActive'])
     ->name('organism.toggleActive');

    Route::post('/culture/test-organism',
        [App\http\Controllers\CultureReportController::class, 'getMapping']
    )->name('edit-mapping-test');
    Route::put('/organism/update_mapping', [App\http\Controllers\CultureReportController::class, 'updateOrganismMapping'])
        ->name('organism.updateMapping');
    Route::post('/organism/toggle-active-mapping', [App\http\Controllers\CultureReportController::class, 'toggleActiveMapping'])
     ->name('organism.toggleActiveMapping');
     Route::get('/organism/active-list-mapping', [App\http\Controllers\CultureReportController::class, 'activeListMapping'])
     ->name('organism.activeListMapping');



     Route::get('/open-dynamic-reports', [App\Http\Controllers\ReportController::class, 'openDynamicReports'])->name('open.dynamic.reports');

  });


// // New Routes- Doctor
Route::middleware(['auth', 'user-access:doctor'])->group(function () {


// Route::get('/doctor/login',[App\Http\Controllers\Doctor\DoctorController::class,'index'])->name('doctor.login');
// Route::post('/doctor/login',[App\Http\Controllers\Doctor\DoctorController::class,'authenticate'])->name('doctor.process.login');
// Route::get('/doctor/logout',[App\Http\Controllers\Doctor\DoctorController::class,'logout'])->name('doctor.logout');
  Route::get('/doctor/dashboard',[App\Http\Controllers\Doctor\DoctorController::class,'dashboard'])->name('doctor.dashboard');
});

// Route::get('new-patient-layout',[PatientController::class,'new_patient_layout'])->name('new_layout.patient');



});



Route::get('license-expired',function(){ return view('errors.license_expired'); })->name('license.expired');

Route::get('enter-license-key',[App\Http\Controllers\admin\LicenseController::class,'index'])->name('enter_license');
Route::post('update-license-key',[App\Http\Controllers\admin\LicenseController::class,'update'])->name('update_license');
Route::post('fetch-license',[App\Http\Controllers\admin\LicenseController::class,'fetch_license'])->name('fetch_license');
Route::post('update-empath-token',[App\Http\Controllers\admin\LicenseController::class,'update_empath_token'])->name('update_empath_token');


//Patient Appointments Routes
Route::group(['prefix' => 'book-appointment'], function () {
    Route::get('/',[\App\Http\Controllers\BookAppointmentController::class,'index'])->name('appt.index');
    Route::get('login',[\App\Http\Controllers\BookAppointmentController::class,'pin_login'])->name('appt.pin_login');
    Route::post('verify-login',[\App\Http\Controllers\BookAppointmentController::class,'pin_login_verify'])->middleware('throttle:login')->name('appt.pin_login_verify');
    Route::get('sign-up',[\App\Http\Controllers\BookAppointmentController::class,'signup'])->name('appt.signup');
    Route::get('search',[\App\Http\Controllers\BookAppointmentController::class,'search'])->name('appt.search');
    Route::get('profile',[\App\Http\Controllers\BookAppointmentController::class,'profile'])->name('appt.profile');

    Route::get('select-doctor/{id}',[\App\Http\Controllers\BookAppointmentController::class,'select_doctor'])->name('appt.select_doctor');
    Route::get('select-hospital/{id}',[\App\Http\Controllers\BookAppointmentController::class,'select_hospital'])->name('appt.select_hospital');
    Route::get('select-hospital-branch/{id}',[\App\Http\Controllers\BookAppointmentController::class,'select_hospital_branch'])->name('appt.select_hospital_branch');
    Route::get('pick-doctor-timing/{id}', [App\Http\Controllers\BookAppointmentController::class, 'pick_doctor_timing'])->name('appt.pick_doctor_timing');
    Route::get('get_appointment_slot_layout',[App\Http\Controllers\BookAppointmentController::class,'get_appointment_slot_layout'])->name('appt.get_appointment_slot_layout');
    Route::any('confirm-booking', [App\Http\Controllers\BookAppointmentController::class, 'confirm_booking'])->name('appt.confirm_booking');
    Route::post('save-confirm-booking', [App\Http\Controllers\BookAppointmentController::class, 'save_confirm_booking'])->name('appt.save_confirm_booking');
    Route::get('pick-doctor-timing-for-reschedule/{id}', [App\Http\Controllers\BookAppointmentController::class, 'pick_doctor_timing_for_reschedule'])->name('appt.pick_doctor_timing_for_reschedule');
    Route::post('confirm-reschedule-booking', [App\Http\Controllers\BookAppointmentController::class, 'confirm_reschedule_booking'])->name('appt.confirm_reschedule_booking');
    Route::get('get_appointment_slot_layout',[App\Http\Controllers\BookAppointmentController::class,'get_appointment_slot_layout'])->name('appt.get_appointment_slot_layout');
    Route::post('save-confirm-reschedule-booking', [App\Http\Controllers\BookAppointmentController::class, 'save_confirm_reschedule_booking'])->name('appt.save_confirm_reschedule_booking');
    Route::get('verify-mobile-number', [App\Http\Controllers\BookAppointmentController::class, 'verify_mobile_number'])->name('appt.verify_mobile_number');
    Route::get('send-otp-to-mobile', [App\Http\Controllers\BookAppointmentController::class, 'send_otp'])->name('appt.send_otp');
    Route::post('verify-mobile-otp', [App\Http\Controllers\BookAppointmentController::class, 'verify_otp'])->middleware('throttle:login')->name('appt.verify_otp');
    Route::get('send-otp-to-doctor', [App\Http\Controllers\BookAppointmentController::class, 'send_doctor_otp'])->name('appt.send_doctor_otp');
    Route::post('verify-doctor-mobile-otp', [App\Http\Controllers\BookAppointmentController::class, 'verify_doctor_otp'])->middleware('throttle:login')->name('appt.verify_doctor_otp');
    Route::get('create-login-pin', [App\Http\Controllers\BookAppointmentController::class, 'create_login_pin'])->name('appt.create_login_pin');
    Route::post('save-login-pin', [App\Http\Controllers\BookAppointmentController::class, 'save_login_pin'])->name('appt.save_login_pin');
    Route::get('pick-patient', [App\Http\Controllers\BookAppointmentController::class, 'pick_or_create_patient_info'])->name('appt.pick_or_create_patient_info');
    Route::post('save-patient-info', [App\Http\Controllers\BookAppointmentController::class, 'save_patient_info'])->name('appt.save_patient_info');
    Route::get('patient-select/{id}', [App\Http\Controllers\BookAppointmentController::class, 'patient_selected'])->name('appt.patient_selected');
    Route::get('patient-login', [App\Http\Controllers\BookAppointmentController::class, 'login'])->name('appt.login');
    Route::get('patient-logout', [App\Http\Controllers\BookAppointmentController::class, 'logout'])->name('appt.logout');
    Route::get('doctor-login', [App\Http\Controllers\BookAppointmentController::class, 'doctor_login'])->name('appt.doctor_login');
    Route::get('doctor-logout', [App\Http\Controllers\BookAppointmentController::class, 'doctor_logout'])->name('appt.doctor_logout');
    Route::post('verify-doctor-login', [App\Http\Controllers\BookAppointmentController::class, 'verify_doctor_login'])->middleware('throttle:login')->name('appt.verify_doctor_login');
});

Route::middleware('patient.auth')->group(function () {

  Route::get('/my-bookings', [App\Http\Controllers\BookAppointmentController::class, 'my_bookings'])->name('appt.my_bookings');
  Route::get('/bookings-info/{id}', [App\Http\Controllers\BookAppointmentController::class, 'bookings_info'])->name('appt.bookings_info');
  Route::get('/booking-history', [App\Http\Controllers\BookAppointmentController::class, 'booking_history'])->name('appt.booking_history');
  Route::get('/get-bookings-list-for-month', [App\Http\Controllers\BookAppointmentController::class, 'get_appointments_for_month'])->name('patient.get_appointments_for_month');
  Route::get('/edit-profile', [App\Http\Controllers\BookAppointmentController::class, 'edit_profile'])->name('appt.edit_profile');
  Route::post('/save-profile', [App\Http\Controllers\BookAppointmentController::class, 'save_profile'])->name('appt.save_profile');
  Route::post('/save_cancellation_form', [App\Http\Controllers\BookAppointmentController::class, 'save_cancellation_form'])->name('appt.save_cancellation_form');

});



Route::middleware('patient.auth')->prefix('patient-panel')->group(function () {

  Route::get('/visits', [App\Http\Controllers\PatientPanelController::class, 'visits'])->name('pat_panel.visits');
  Route::get('/visit-details/{id}', [App\Http\Controllers\PatientPanelController::class, 'visit_detail'])->name('pat_panel.visit_detail');
  Route::get('/health-tracker', [App\Http\Controllers\PatientPanelController::class, 'health_tracker'])->name('pat_panel.health_tracker');
  Route::get('/get-member-latest-observations', [App\Http\Controllers\PatientPanelController::class, 'get_member_latest_observations'])->name('pat_panel.get_member_latest_observations');
  Route::get('/get-member-observations', [App\Http\Controllers\PatientPanelController::class, 'get_member_observations'])->name('pat_panel.get_member_observations');
  Route::get('/get-bp-health-tracker-form', [App\Http\Controllers\PatientPanelController::class, 'get_bp_form'])->name('pat_panel.get_bp_form');
  Route::get('edit-bp-health-tracker-form', [App\Http\Controllers\PatientPanelController::class, 'edit_bp_form'])->name('pat_panel.edit_bp_form');
  Route::post('update-bp-health-tracker-form/{id}', [App\Http\Controllers\PatientPanelController::class, 'update_bp_form'])->name('pat_panel.update_bp_form');
  Route::post('/save-bp-health-tracker-form', [App\Http\Controllers\PatientPanelController::class, 'save_bp_form'])->name('pat_panel.save_bp_form');
  Route::post('/delete-health-tracker-form', [App\Http\Controllers\PatientPanelController::class, 'delete_health_record'])->name('pat_panel.delete_health_record');

  Route::get('/get-wh-health-tracker-form', [App\Http\Controllers\PatientPanelController::class, 'get_weight_height_form'])->name('pat_panel.get_weight_height_form');
  Route::post('/save-wh-health-tracker-form', [App\Http\Controllers\PatientPanelController::class, 'save_weight_height_form'])->name('pat_panel.save_weight_height_form');
  Route::get('/edit-wh-health-tracker-form', [App\Http\Controllers\PatientPanelController::class, 'edit_weight_height_form'])->name('pat_panel.edit_weight_height_form');
  Route::post('/update-wh-health-tracker-form/{id}', [App\Http\Controllers\PatientPanelController::class, 'update_weight_height_form'])->name('pat_panel.update_weight_height_form');

  Route::get('/get-sugar-health-tracker-form', [App\Http\Controllers\PatientPanelController::class, 'get_sugar_form'])->name('pat_panel.get_sugar_form');
  Route::post('/save-sugar-health-tracker-form', [App\Http\Controllers\PatientPanelController::class, 'save_sugar_form'])->name('pat_panel.save_sugar_form');
  Route::get('/save-sugar-health-tracker-form',  [App\Http\Controllers\PatientPanelController::class, 'edit_sugar_form'])->name('pat_panel.edit_sugar_form');
  Route::post('/update-sugar-health-tracker-form/{id}', [App\Http\Controllers\PatientPanelController::class, 'update_sugar_form'])->name('pat_panel.update_sugar_form');

  Route::post('/save-patient-and-link-member', [App\Http\Controllers\PatientPanelController::class, 'save_patient_and_link_member'])->name('pat_panel.save_patient_and_link_member');
  Route::get('/link-exiting-member/{visit_id}/{pid}/', [App\Http\Controllers\PatientPanelController::class, 'link_existing_member'])->name('pat_panel.link_existing_member');
});

Route::middleware('doctor.auth')->prefix('doctor-panel')->group(function () {

  Route::get('/dashboard', [App\Http\Controllers\BookAppointmentController::class, 'doctor_dashboard'])->name('appt.doctor_dashboard');
  Route::get('/appointments', [App\Http\Controllers\BookAppointmentController::class, 'doctor_appointments'])->name('appt.doctor_appointments');
  Route::get('/appointments-info/{id}', [App\Http\Controllers\BookAppointmentController::class, 'doctor_appointment_info'])->name('appt.doctor_appointment_info');
  Route::get('/profile', [App\Http\Controllers\BookAppointmentController::class, 'doctor_profile'])->name('appt.doctor_profile');
  Route::get('/edit_doctor_profile', [App\Http\Controllers\BookAppointmentController::class, 'edit_doctor_profile'])->name('appt.edit_doctor_profile');
  Route::post('/save_doctor_profile', [App\Http\Controllers\BookAppointmentController::class, 'save_doctor_profile'])->name('appt.save_doctor_profile');
  Route::get('/doctor_login_pin', [App\Http\Controllers\BookAppointmentController::class, 'doctor_login_pin'])->name('appt.doctor_login_pin');
  Route::post('/save_doctor_login_pin', [App\Http\Controllers\BookAppointmentController::class, 'save_doctor_login_pin'])->name('appt.save_doctor_login_pin');
  Route::get('/settings', [App\Http\Controllers\BookAppointmentController::class, 'doctor_settings'])->name('appt.doctor_settings');
  Route::get('/doctor_status_update', [App\Http\Controllers\BookAppointmentController::class, 'doctor_status_update'])->name('appt.doctor_status_update');
  Route::match(['get', 'post'], '/doctor_approve_hospital', [App\Http\Controllers\BookAppointmentController::class, 'doctor_approve_hospital'])->name('appt.doctor_approve_hospital');
  Route::get('/doctor_db_status_update', [App\Http\Controllers\BookAppointmentController::class, 'doctor_db_status_update'])->name('appt.doctor_db_status_update');
  Route::get('/doctor_primary_hospital_update', [App\Http\Controllers\BookAppointmentController::class, 'doctor_primary_hospital_update'])->name('appt.doctor_primary_hospital_update');

});

Route::any('/api/lis-data', [App\Http\Controllers\TestController::class,'lis_data']);
Route::post('/api/lis-data-raw', [App\Http\Controllers\TestController::class,'lis_data_raw']);

Route::get('/test-pdf-header-footer', [App\Http\Controllers\TestController::class, 'addHeaderFooter'])->name('pdf.add-header-footer');


//Display OPD Queue
Route::get('show-opd-queue',[App\Http\Controllers\admin\PatientController::class,'show_opd_queue'])->name('show_opd_queue');
Route::get('show-opd-queue-single',[App\Http\Controllers\admin\PatientController::class,'show_opd_queue_single'])->name('show_opd_queue_single');
Route::get('fetch-opd-queue-data',[App\Http\Controllers\admin\PatientController::class,'get_opd_queue_data'])->name('get_opd_queue_data');
Route::get('fetch-opd-queue-multiple-doctor-data',[App\Http\Controllers\admin\PatientController::class,'get_opd_queue_multiple_doctor_data'])->name('get_opd_queue_multiple_doctor_data');



Route::prefix('api')->group(function () {
  Route::get('/validate-redirect', [App\Http\Controllers\ApiController::class, 'validate_redirect']);
  Route::post('/login', [\App\Http\Controllers\ApiController::class,'login'])->middleware('throttle:login')->name('api.login');
  Route::post('/register-mobile-number', [\App\Http\Controllers\ApiController::class,'register_mobile_number'])->name('api.register_mobile_number');
  Route::post('/verify-otp-pin', [\App\Http\Controllers\ApiController::class,'verify_otp_pin'])->middleware('throttle:login')->name('api.verify_otp_pin');
  Route::post('/authenticate-user-request', [\App\Http\Controllers\ApiController::class,'auth_user_request'])->name('api.auth_user_request');
  Route::post('/get-all-members', [\App\Http\Controllers\ApiController::class,'get_all_members'])->name('api.get_all_members');
  Route::post('/get-visits-list', [\App\Http\Controllers\ApiController::class,'get_visits_list'])->name('api.get_visits_list');
  Route::post('/create-new-member', [\App\Http\Controllers\ApiController::class,'create_new_member'])->name('api.create_new_member');
  Route::post('/save-hospital-visit', [\App\Http\Controllers\ApiController::class,'save_hospital_visit'])->name('api.save_hospital_visit');
  Route::get('/fetch-recordings/{id}', [\App\Http\Controllers\ApiController::class,'recordings'])->name('api.recordings');
  Route::get('/download/recordings/{id}',[\App\Http\Controllers\HomeController::class,'download_recordings'])->name('recordings.download');
  Route::post('/patient/video/consent',[\App\Http\Controllers\HomeController::class,'store_video_consent'])->name('recordings.store2');
  Route::get('/recordings/sync', [\App\Http\Controllers\HomeController::class,'syncRecordings'])->name('sync.recordings');


  Route::post('/recordings/mark-downloaded', [\App\Http\Controllers\HomeController::class,'markRecordingDownloaded'])->name('mark.downloaded');
  Route::get('/check-phone-number', [\App\Http\Controllers\ApiController::class, 'checkPhoneNumber'])->name('api.check_phone_number');

});

// ABHA Cloud Relay Integration Routes
Route::middleware(['auth', 'module-license:abha'])->prefix('abha')->name('abha.')->group(function () {
    // Rate-limited OTP request and verification routes (prevents brute-force)
    Route::middleware(['throttle:10,1'])->group(function () {
        Route::post('/request-aadhaar-otp', [\App\Http\Controllers\abdm\AbdmController::class, 'requestAadhaarOtp'])->name('request_aadhaar_otp');
        Route::post('/verify-aadhaar-otp', [\App\Http\Controllers\abdm\AbdmController::class, 'verifyAadhaarOtp'])->name('verify_aadhaar_otp');
        Route::post('/send-mobile-otp', [\App\Http\Controllers\abdm\AbdmController::class, 'sendMobileOtp'])->name('send_mobile_otp');
        Route::post('/verify-mobile-otp', [\App\Http\Controllers\abdm\AbdmController::class, 'verifyMobileOtp'])->name('verify_mobile_otp');
        Route::post('/request-dl-otp', [\App\Http\Controllers\abdm\AbdmController::class, 'requestDlOtp'])->name('request_dl_otp');
        Route::post('/verify-dl-otp', [\App\Http\Controllers\abdm\AbdmController::class, 'verifyDlOtp'])->name('verify_dl_otp');
        Route::post('/link-existing/search', [\App\Http\Controllers\abdm\AbdmController::class, 'linkExistingSearch'])->name('link_existing_search');
        Route::post('/link-existing/request-otp', [\App\Http\Controllers\abdm\AbdmController::class, 'linkExistingRequestOtp'])->name('link_existing_request_otp');
        Route::post('/link-existing/verify-otp', [\App\Http\Controllers\abdm\AbdmController::class, 'linkExistingVerifyOtp'])->name('link_existing_verify_otp');
        Route::post('/link-existing/select-account', [\App\Http\Controllers\abdm\AbdmController::class, 'linkExistingSelectAccount'])->name('link_existing_select_account');
    });

    Route::post('/suggest-addresses', [\App\Http\Controllers\abdm\AbdmController::class, 'suggestAbhaAddresses'])->name('suggest_addresses');
    Route::post('/create-abha-address', [\App\Http\Controllers\abdm\AbdmController::class, 'createAbhaAddress'])->name('create_abha_address');
    Route::post('/download-card', [\App\Http\Controllers\abdm\AbdmController::class, 'downloadCard'])->name('download_card');
    Route::get('/abdm/card/pdf', [\App\Http\Controllers\abdm\AbdmController::class, 'viewCardPdf'])->name('abdm.card.pdf');
    Route::post('/abdm/card/pdf', [\App\Http\Controllers\abdm\AbdmController::class, 'viewCardPdf'])->name('abdm.card.pdf.post');
    Route::get('/generate-encryption-keys', [\App\Http\Controllers\abdm\AbdmController::class, 'generateEncryptionKeys'])->name('generate_encryption_keys');
    Route::post('/encrypt-data', [\App\Http\Controllers\abdm\AbdmController::class, 'encryptData'])->name('encrypt_data');
    Route::post('/fetch-care-contexts', [\App\Http\Controllers\abdm\AbdmController::class, 'fetchCareContexts'])->name('fetch_care_contexts');
    Route::post('/request-health-data', [\App\Http\Controllers\abdm\AbdmController::class, 'requestHealthData'])->name('request_health_data');

    // M3 HIU Patient Consent & Health Records
    Route::get('/m3/fidelius-status', [\App\Http\Controllers\abdm\AbdmController::class, 'checkFideliusStatus'])->name('m3.fidelius_status');
    Route::post('/m3/consent-request', [\App\Http\Controllers\abdm\AbdmController::class, 'initiateHiuConsent'])->name('m3.consent_request');
    Route::post('/m3/consent-status', [\App\Http\Controllers\abdm\AbdmController::class, 'checkHiuConsentStatus'])->name('m3.consent_status');
    Route::get('/m3/consent-history', [\App\Http\Controllers\abdm\AbdmController::class, 'getHiuConsentHistory'])->name('m3.consent_history');
    Route::match(['get', 'post'], '/m3/consent-details', [\App\Http\Controllers\abdm\AbdmController::class, 'getHiuConsentDetails'])->name('m3.consent_details');
    Route::post('/m3/clear-consent-history', [\App\Http\Controllers\abdm\AbdmController::class, 'clearHiuConsentHistory'])->name('m3.clear_consent_history');
    Route::post('/m3/fetch-records', [\App\Http\Controllers\abdm\AbdmController::class, 'fetchHiuHealthRecords'])->name('m3.fetch_records');

    // ABHA Profile Edit, Live Fetch, OTP Update & Real-Time HIMS Sync
    Route::get('/profile/get-for-edit', [\App\Http\Controllers\abdm\AbdmController::class, 'getAbhaProfileForEdit'])->name('profile.get_for_edit');
    Route::post('/profile/request-update-otp', [\App\Http\Controllers\abdm\AbdmController::class, 'requestProfileUpdateOtp'])->name('profile.request_update_otp');
    Route::post('/profile/verify-sync', [\App\Http\Controllers\abdm\AbdmController::class, 'verifyAndSyncAbhaProfile'])->name('profile.verify_sync');

    // ABHA Account Management: Deactivate / Delete & Delink Mobile
    Route::post('/account/deactivate/request-otp', [\App\Http\Controllers\abdm\AbdmController::class, 'requestDeactivateOtp'])->name('account.deactivate.request_otp');
    Route::post('/account/deactivate/confirm', [\App\Http\Controllers\abdm\AbdmController::class, 'confirmDeactivate'])->name('account.deactivate.confirm');
    Route::post('/account/delink-mobile/request-otp', [\App\Http\Controllers\abdm\AbdmController::class, 'requestDelinkMobileOtp'])->name('account.delink_mobile.request_otp');
    Route::post('/account/delink-mobile/confirm', [\App\Http\Controllers\abdm\AbdmController::class, 'confirmDelinkMobile'])->name('account.delink_mobile.confirm');
});



// Top-level ABHA Card PDF Viewer and Download Route Aliases
Route::middleware(['auth', 'module-license:abha'])->group(function () {
    Route::get('/abdm', [\App\Http\Controllers\abdm\AbdmController::class, 'viewDashboardPage'])->name('abdm.index');
    Route::get('/abdm/dashboard', [\App\Http\Controllers\abdm\AbdmController::class, 'viewDashboardPage'])->name('abdm.dashboard');
    Route::get('/abdm/dashboard/stats', [\App\Http\Controllers\abdm\AbdmController::class, 'getDashboardStats'])->name('abdm.dashboard.stats');
    Route::get('/abdm/scan-share', [\App\Http\Controllers\abdm\AbdmController::class, 'viewScanSharePage'])->name('abdm.scan_share');
    Route::get('/abdm/record-linking', [\App\Http\Controllers\abdm\AbdmController::class, 'viewM2HipConsentsPage'])->name('abdm.record_linking');
    Route::get('/abdm/fetch-records', [\App\Http\Controllers\abdm\AbdmController::class, 'viewM3ConsentPage'])->name('abdm.fetch_records');
    Route::get('/abdm/logs', [\App\Http\Controllers\abdm\AbdmController::class, 'viewAbdmLogsPage'])->name('abdm.logs');
    Route::get('/abdm/logs/data', [\App\Http\Controllers\abdm\AbdmController::class, 'getUnifiedLogsData'])->name('abdm.logs.data');
    Route::get('/abdm/logs/detail/{id}', [\App\Http\Controllers\abdm\AbdmController::class, 'getUnifiedLogDetail'])->name('abdm.logs.detail');
    Route::get('/abdm/discovery/monitor-data', [\App\Http\Controllers\abdm\AbdmController::class, 'getInboundDiscoveryMonitorData'])->name('abdm.discovery.monitor_data');
    Route::get('/abdm/discovery/detail/{id}', [\App\Http\Controllers\abdm\AbdmController::class, 'getInboundDiscoveryDetail'])->name('abdm.discovery.detail');
    Route::get('/abdm/incentives', [\App\Http\Controllers\abdm\AbdmController::class, 'viewIncentivesPage'])->name('abdm.incentives');
    Route::get('/abdm/incentives/data', [\App\Http\Controllers\abdm\AbdmController::class, 'getIncentivesData'])->name('abdm.incentives.data');
    Route::get('/abdm/incentives/export-csv', [\App\Http\Controllers\abdm\AbdmController::class, 'exportIncentivesCsv'])->name('abdm.incentives.export_csv');

    // Backward-Compatible Milestone Aliases
    Route::get('/abdm/m3', [\App\Http\Controllers\abdm\AbdmController::class, 'viewM3ConsentPage'])->name('abdm.m3.index');
    Route::get('/abdm/m2', [\App\Http\Controllers\abdm\AbdmController::class, 'viewM2HipConsentsPage'])->name('abdm.m2.index');
    Route::get('/abdm/m2/logs', [\App\Http\Controllers\abdm\AbdmController::class, 'getM2LogsData'])->name('abdm.m2.logs_data');
    Route::get('/abdm/m2/log/{id}', [\App\Http\Controllers\abdm\AbdmController::class, 'getM2LogDetail'])->name('abdm.m2.log_detail');
    Route::get('/abdm/m2/consents', [\App\Http\Controllers\abdm\AbdmController::class, 'getM2HipConsentsData'])->name('abdm.m2.consents_data');
    Route::get('/abdm/m2/consent/{id}', [\App\Http\Controllers\abdm\AbdmController::class, 'getM2HipConsentDetail'])->name('abdm.m2.consent_detail');
    Route::get('/abdm/m1', [\App\Http\Controllers\abdm\AbdmController::class, 'viewM1AbhaDashboardPage'])->name('abdm.m1.index');
    Route::get('/abdm/m1/data', [\App\Http\Controllers\abdm\AbdmController::class, 'getM1DashboardData'])->name('abdm.m1.data');
    Route::get('/abdm/m1/scan-share', [\App\Http\Controllers\abdm\AbdmController::class, 'getM1ScanShareData'])->name('abdm.m1.scan_share');
    Route::get('/abdm/m1/log/{id}', [\App\Http\Controllers\abdm\AbdmController::class, 'getM1LogDetail'])->name('abdm.m1.log_detail');
    Route::post('/abdm/m1/scan-share/clear-queue', [\App\Http\Controllers\abdm\AbdmController::class, 'clearM1ScanShareQueue'])->name('abdm.m1.clear_scan_share');
    Route::post('/abdm/m1/scan-share/mark-registered', [\App\Http\Controllers\abdm\AbdmController::class, 'markM1ScanShareRegistered'])->name('abdm.m1.mark_registered');
    Route::get('/abha/m3/search-patient', [\App\Http\Controllers\abdm\AbdmController::class, 'searchPatientForM3'])->name('abha.m3.search_patient');
    Route::post('/abha/m3/consent-details', [\App\Http\Controllers\abdm\AbdmController::class, 'getHiuConsentDetails'])->name('abha.m3.consent_details_post');
    Route::post('/abdm/consent/revoke', [\App\Http\Controllers\abdm\AbdmController::class, 'doctorRevokeConsent'])->name('abdm.web.consent.revoke');
    Route::get('/abdm/card/pdf', [\App\Http\Controllers\abdm\AbdmController::class, 'viewCardPdf'])->name('abdm.card.pdf');
    Route::post('/abdm/card/pdf', [\App\Http\Controllers\abdm\AbdmController::class, 'viewCardPdf'])->name('abdm.card.pdf.post');
    Route::post('/download-card', [\App\Http\Controllers\abdm\AbdmController::class, 'downloadCard'])->name('download_card');

    // Deep Link SMS Notification & Care Context Approval (ABDM Milestone 2)
    Route::post('/abdm/link/sms-notify', [\App\Http\Controllers\abdm\AbdmController::class, 'sendLinkSmsNotify'])->name('abdm.link.sms_notify');
    Route::post('/abdm/care-context/notify-sms', [\App\Http\Controllers\abdm\AbdmController::class, 'notifyPatientCareContextSms'])->name('abdm.care_context.notify_sms');
    Route::post('/abdm/care-context/approve', [\App\Http\Controllers\abdm\AbdmController::class, 'approveCareContextForAbdm'])->name('abdm.care_context.approve');
    Route::post('/abdm/care-context/upload', [\App\Http\Controllers\abdm\AbdmController::class, 'approveCareContextForAbdm'])->name('abdm.care_context.upload');
    Route::get('/abdm/patients/search-unlinked', [\App\Http\Controllers\abdm\AbdmController::class, 'searchUnlinkedPatients'])->name('abdm.patients.search_unlinked');

    // Patient Care Context Linking Management Page & APIs
    Route::get('/abdm/care-contexts', [\App\Http\Controllers\abdm\AbdmController::class, 'viewCareContextsPage'])->name('abdm.care_contexts');
    Route::get('/abdm/care-contexts/data', [\App\Http\Controllers\abdm\AbdmController::class, 'getCareContextsData'])->name('abdm.care_contexts.data');
    Route::get('/abdm/care-contexts/unlinked-patients', [\App\Http\Controllers\abdm\AbdmController::class, 'getUnlinkedPatientsData'])->name('abdm.care_contexts.unlinked_patients');
    Route::post('/abdm/care-contexts/check-token', [\App\Http\Controllers\abdm\AbdmController::class, 'checkPatientCareContextToken'])->name('abdm.care_contexts.check_token');
    Route::post('/abdm/care-contexts/link', [\App\Http\Controllers\abdm\AbdmController::class, 'linkCareContext'])->name('abdm.care_contexts.link');
    Route::post('/abdm/care-contexts/bulk-link', [\App\Http\Controllers\abdm\AbdmController::class, 'bulkLinkCareContexts'])->name('abdm.care_contexts.bulk_link');
    Route::post('/abdm/care-contexts/otp-init', [\App\Http\Controllers\abdm\AbdmController::class, 'initCareContextOtpLink'])->name('abdm.care_contexts.otp_init');
    Route::post('/abdm/care-contexts/otp-confirm', [\App\Http\Controllers\abdm\AbdmController::class, 'confirmCareContextOtpLink'])->name('abdm.care_contexts.otp_confirm');
    Route::get('/abdm/care-contexts/detail/{id}', [\App\Http\Controllers\abdm\AbdmController::class, 'getCareContextDetail'])->name('abdm.care_contexts.detail');
    Route::post('/abdm/care-contexts/sync-visits', [\App\Http\Controllers\abdm\AbdmController::class, 'syncVisitsToCareContexts'])->name('abdm.care_contexts.sync_visits');
});



