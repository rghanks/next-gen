<?php

declare(strict_types=1);

use App\Http\Controllers\Lab\AuditController;
use App\Http\Controllers\Lab\BillingController;
use App\Http\Controllers\Lab\DashboardController;
use App\Http\Controllers\Lab\LabOrderDeskController;
use App\Http\Controllers\Lab\LabSettingController;
use App\Http\Controllers\Lab\ReportController;
use App\Http\Controllers\Lab\ResultController;
use App\Http\Controllers\Lab\SpecimenController;
use App\Http\Controllers\Lab\TestMasterController;
use App\Http\Controllers\Lab\VerificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('lab')->name('lab.')->group(function () {

    // Public / Signed verification route (exempt from auth)
    Route::get('reports/{order}/verify-public/{version}', [ReportController::class, 'verifyPublic'])
        ->name('reports.verify-public')
        ->middleware('signed');

    // Web Views & APIs (protected by web and auth middleware)
    Route::middleware(['web', 'auth'])->group(function () {

        // System Overview Dashboard
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('api/dashboard/stats', [DashboardController::class, 'apiStats'])->name('api.dashboard.stats');

        // A. Billing Desk
        Route::get('/', fn () => redirect()->route('lab.billing.index'));
        Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
        Route::get('billing/receipt/{order}', [BillingController::class, 'receipt'])->name('billing.receipt')->middleware('throttle:60,1');
        Route::get('billing/barcode/{order}', [BillingController::class, 'barcodeLabel'])->name('billing.barcode')->middleware('throttle:60,1');
        Route::get('billing/daily-summary', [BillingController::class, 'dailySummary'])->name('billing.daily-summary');
        Route::get('billing/{bill}/details', [BillingController::class, 'billDetails'])->name('billing.details');
        Route::post('billing/{bill}/update', [BillingController::class, 'updateBill'])->name('billing.update');
        Route::post('billing/{bill}/save-full', [BillingController::class, 'saveFullBill'])->name('billing.save-full');
        Route::post('billing/{bill}/items/{item}/remove', [BillingController::class, 'removeItem'])->name('billing.remove-item');
        Route::post('billing/{bill}/settle-payment', [BillingController::class, 'settlePayment'])->name('billing.settle-payment');

        // B. Unified Lab Orders & Worklist (Sample Collection + Result Entry + Tracking)
        Route::get('orders', [LabOrderDeskController::class, 'index'])->name('orders.index');
        Route::post('orders/collect-sample', [LabOrderDeskController::class, 'collectSample'])->name('orders.collect-sample');
        Route::post('orders/update-sample-no', [LabOrderDeskController::class, 'updateSampleNumber'])->name('orders.update-sample-no');
        Route::post('orders/set-sample-series', [LabOrderDeskController::class, 'setSampleSeries'])->name('orders.set-sample-series');
        Route::get('orders/next-sample-no', [LabOrderDeskController::class, 'getNextSampleNumber'])->name('orders.next-sample-no');
        Route::get('billing/next-sample-no', [BillingController::class, 'nextSampleNumber'])->name('billing.next-sample-no');
        Route::post('orders/approve', [LabOrderDeskController::class, 'approveOrder'])->name('orders.approve');
        Route::post('orders/reject', [LabOrderDeskController::class, 'rejectOrder'])->name('orders.reject');

        // C. Specimen Worklist
        Route::get('specimens', [SpecimenController::class, 'index'])->name('specimens.index');
        Route::post('specimens/collect', [SpecimenController::class, 'collect'])->name('specimens.collect');
        Route::post('specimens/receive', [SpecimenController::class, 'receive'])->name('specimens.receive');
        Route::post('specimens/reject', [SpecimenController::class, 'reject'])->name('specimens.reject');

        // C. Result Entry
        Route::get('results', [ResultController::class, 'index'])->name('results.index');
        Route::get('results/{order}/entry', [ResultController::class, 'entry'])->name('results.entry');
        Route::post('results/save', [ResultController::class, 'save'])->name('results.save');
        Route::post('results/acknowledge-critical', [ResultController::class, 'acknowledgeCritical'])->name('results.ack-critical');
        Route::post('results/outsource-upload', [ResultController::class, 'outsourceUpload'])->name('results.outsource-upload');

        // D. Verification Queue
        Route::get('verification', [VerificationController::class, 'index'])->name('verification.index');
        Route::post('verification/validate-batch', [VerificationController::class, 'validateBatch'])->name('verification.validate');
        Route::post('verification/verify-batch', [VerificationController::class, 'verifyBatch'])->name('verification.verify');
        Route::post('verification/reject', [VerificationController::class, 'rejectBack'])->name('verification.reject');

        // E. Report Engine
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/{order}/view', [ReportController::class, 'viewHtml'])->name('reports.view');
        Route::get('reports/{order}/print', [ReportController::class, 'printPdf'])->name('reports.print');
        Route::get('reports/{order}/pdf', [ReportController::class, 'downloadPdf'])->name('reports.pdf')->middleware('throttle:30,1');
        Route::post('reports/{order}/amend', [ReportController::class, 'amend'])->name('reports.amend');
        Route::post('reports/{order}/whatsapp', [ReportController::class, 'sendWhatsApp'])->name('reports.whatsapp')->middleware('throttle:20,1');

        // F. Audit Trail
        Route::get('audit', [AuditController::class, 'index'])->name('audit.index');

        // G. Admin Masters (Tests, Panels, Parameters, Formulas, Pricing)
        Route::prefix('masters')->name('masters.')->group(function () {
            Route::get('tests', [TestMasterController::class, 'index'])->name('tests.index');
            Route::get('tests/create', [TestMasterController::class, 'create'])->name('tests.create');
            Route::post('tests', [TestMasterController::class, 'store'])->name('tests.store');
            Route::get('tests/{test}/edit', [TestMasterController::class, 'edit'])->name('tests.edit');
            Route::post('tests/{test}/update', [TestMasterController::class, 'update'])->name('tests.update');
            Route::post('tests/{test}/toggle', [TestMasterController::class, 'toggleStatus'])->name('tests.toggle');
            Route::post('tests/{test}/parameters', [TestMasterController::class, 'saveParameters'])->name('tests.parameters');
            Route::post('tests/{test}/pricing', [TestMasterController::class, 'savePricing'])->name('tests.pricing');
            Route::post('tests/{test}/charge', [TestMasterController::class, 'updateChargeInline'])->name('tests.charge');
        });

        // H. Laboratory Settings (Lab Profile, Header/Footer, GST, Signatures, Logo, Print Margins)
        Route::get('settings', [LabSettingController::class, 'index'])->name('settings.index');
        Route::post('settings', [LabSettingController::class, 'update'])->name('settings.update');
        Route::post('settings/print', [LabSettingController::class, 'updatePrintSettings'])->name('settings.update-print');
        Route::post('settings/sample-series', [LabSettingController::class, 'lockSampleSeries'])->name('settings.lock-sample-series');

        // JSON API Endpoints
        Route::prefix('api')->name('api.')->group(function () {
            // Patient search & service search
            Route::get('patients/search', [BillingController::class, 'searchPatients'])->name('patients.search');
            Route::get('services/search', [BillingController::class, 'searchServices'])->name('services.search');
            Route::get('billing/today-visits', [BillingController::class, 'todayVisits'])->name('billing.today-visits');
            Route::post('referrals/store', [BillingController::class, 'storeReferral'])->name('referrals.store');
            Route::get('masters/search-tests', [TestMasterController::class, 'searchAvailableTests'])->name('masters.search-tests');
            Route::post('billing/store', [BillingController::class, 'store'])->name('billing.store');
            Route::post('billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');

            // Specimen scan API
            Route::post('specimens/scan', [SpecimenController::class, 'scanBarcode'])->name('specimens.scan');

            // Live formula calculation preview API
            Route::post('results/formula-preview', [ResultController::class, 'previewFormula'])->name('results.formula-preview');
        });
    });
});
