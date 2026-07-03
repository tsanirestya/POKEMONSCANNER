<?php

use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\ScanSyncController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => view('login'))->name('login');
});

Route::post('/logout', function () {
    Auth::logout();

    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::get('/', fn () => redirect()->route('login'));

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');
    Route::get('/admin/vendors', fn () => view('admin.vendors'))->name('admin.vendors');
    Route::get('/admin/products', fn () => view('admin.products'))->name('admin.products');
    Route::get('/admin/products/import', fn () => view('admin.products-import'))->name('admin.products.import');
});

Route::middleware(['auth', 'role:admin,operator'])->group(function () {
    Route::get('/scan', fn () => view('scan'))->name('scan');
    Route::post('/scan/submit', [ScanSyncController::class, 'submit'])->name('scan.submit');
    Route::get('/scan/master-cache', [ScanSyncController::class, 'masterCache'])->name('scan.master-cache');
    Route::get('/laporan', fn () => view('laporan'))->name('laporan');
    Route::get('/laporan/export', [ReportExportController::class, 'download'])->name('laporan.export');
});
