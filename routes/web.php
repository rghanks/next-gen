<?php

declare(strict_types=1);

use App\Http\Controllers\LicenseController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('lab.billing.index');
});

// Licensing routes
Route::get('/license/activate', [LicenseController::class, 'activate'])->name('license.activate');
Route::post('/license/activate', [LicenseController::class, 'submit'])->name('license.submit');
Route::get('/license/machine-id', [LicenseController::class, 'machineId'])->name('license.machine-id');

require __DIR__.'/lab.php';
