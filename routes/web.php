<?php

use App\Http\Controllers\BookingRekonsiliasiExportController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\ScanSyncController;
use App\Models\Booking;
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

Route::get('/', fn () => auth()->check()
    ? redirect(auth()->user()->homeRoute())
    : redirect()->route('login'));

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');
    Route::get('/admin/vendors', fn () => view('admin.vendors'))->name('admin.vendors');
    Route::get('/admin/products', fn () => view('admin.products'))->name('admin.products');
    Route::get('/admin/products/import', fn () => view('admin.products-import'))->name('admin.products.import');
    Route::get('/admin/users', fn () => view('admin.users'))->name('admin.users');
});

Route::middleware(['auth', 'role:admin,operator,spg'])->group(function () {
    Route::get('/booking', fn () => view('booking'))->name('booking');
    Route::get('/booking/riwayat', fn () => view('booking-riwayat'))->name('booking.riwayat');
    Route::get('/booking/{booking}/struk', function (Booking $booking) {
        // SPG hanya boleh lihat struk miliknya; admin/operator (store keeper) boleh semua.
        abort_unless(
            auth()->user()->isAdmin() || auth()->user()->isOperator() || $booking->user_id === auth()->id(),
            403,
        );

        return view('booking-struk', ['booking' => $booking->load('items.product', 'user')]);
    })->name('booking.struk');
});

Route::middleware(['auth', 'role:admin,operator'])->group(function () {
    // FR-BOOK-04: rekonsiliasi store keeper — SPG 403 (didobel guard boot() komponen).
    Route::get('/booking/rekonsiliasi', fn () => view('booking-rekonsiliasi'))->name('booking.rekonsiliasi');
    Route::get('/booking/rekonsiliasi/export', [BookingRekonsiliasiExportController::class, 'download'])->name('booking.rekonsiliasi.export');
    Route::get('/scan', fn () => view('scan'))->name('scan');
    Route::post('/scan/submit', [ScanSyncController::class, 'submit'])->name('scan.submit');
    Route::get('/scan/master-cache', [ScanSyncController::class, 'masterCache'])->name('scan.master-cache');
    Route::get('/laporan', fn () => view('laporan'))->name('laporan');
    Route::get('/laporan/export', [ReportExportController::class, 'download'])->name('laporan.export');
});
