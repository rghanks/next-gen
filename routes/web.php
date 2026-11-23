<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\LicenseController;
use Illuminate\Support\Facades\Route;

// Guest Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.attempt');
});

// Authenticated Session & Security Routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn () => redirect()->route('lab.dashboard'))->name('dashboard');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::post('/user/password', [LoginController::class, 'updatePassword'])->name('user.password.update');
});

Route::get('/', function () {
    return redirect()->route('lab.billing.index');
});

// Licensing routes
Route::get('/license/activate', [LicenseController::class, 'activate'])->name('license.activate');
Route::post('/license/activate', [LicenseController::class, 'submit'])->name('license.submit');
Route::get('/license/machine-id', [LicenseController::class, 'machineId'])->name('license.machine-id');

require __DIR__.'/lab.php';
