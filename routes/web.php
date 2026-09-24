<?php

use App\Http\Controllers\PersonController;
use App\Http\Controllers\ScanController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('persons', PersonController::class)->only(['index', 'create', 'store', 'show']);

    Route::resource('scans', ScanController::class)->only(['index', 'create', 'store', 'show']);
    Route::get('scans/{scan}/file', [ScanController::class, 'file'])->name('scans.file');
    Route::get('scans/{scan}/thumbnail', [ScanController::class, 'thumbnail'])->name('scans.thumbnail');
});

require __DIR__.'/settings.php';
