<?php

use App\Http\Controllers\ContactRecordController;
use App\Http\Controllers\CustomerController;
use Inertia\Inertia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProxyCustomerLatestController;
use App\Http\Controllers\ProxyLatestInspectionRefreshController;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/proxy/customer-latest', ProxyCustomerLatestController::class)
    ->name('get-customers');

Route::post('/proxy/latest-inspection-refresh', ProxyLatestInspectionRefreshController::class)
    ->middleware(['auth', 'verified'])
    ->name('proxy.latest-inspection.refresh');

Route::get('/customer/intake/{legacyId}', [CustomerController::class, 'start'])->middleware(['auth', 'verified'])->name('customer.intake.start');
Route::get('/customers', [CustomerController::class, 'index'])->middleware(['auth', 'verified'])->name('customer.index');
Route::get('/customer/create', [CustomerController::class, 'create'])->middleware(['auth', 'verified'])->name('customer.create');
Route::post('/customers', [CustomerController::class, 'store'])->middleware(['auth', 'verified'])->name('customer.store');
Route::get('/customer/{customer}', [CustomerController::class, 'show'])->middleware(['auth', 'verified'])->name('customer.show');
Route::put('/customer/{customer}', [CustomerController::class, 'update'])->middleware(['auth','verified'])->name('customer.update');

Route::post('/customer/{customer}/records', [ContactRecordController::class, 'store'])
    ->middleware(['auth','verified'])->name('customer.records.store');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
