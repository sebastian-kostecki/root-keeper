<?php

use App\Http\Controllers\EventScanController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\ScanController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('persons', PersonController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);

    Route::resource('scans', ScanController::class)->only(['index', 'create', 'store', 'show']);
    Route::get('scans/{scan}/file', [ScanController::class, 'file'])->name('scans.file');
    Route::get('scans/{scan}/thumbnail', [ScanController::class, 'thumbnail'])->name('scans.thumbnail');

    Route::post('events/{event}/scans', [EventScanController::class, 'store'])->name('events.scans.store');
    Route::delete('events/{event}/scans/{scan}', [EventScanController::class, 'destroy'])->name('events.scans.destroy');
});

require __DIR__.'/settings.php';
