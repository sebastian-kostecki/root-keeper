<?php

use App\Http\Controllers\EventScanController;
use App\Http\Controllers\MarriageController;
use App\Http\Controllers\ParentChildRelationController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\TreeController;
use App\Http\Controllers\TreeRootPersonController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('persons', PersonController::class)->only(['index', 'store', 'show', 'edit', 'update', 'destroy']);

    Route::get('tree', [TreeController::class, 'index'])->name('tree.index');
    Route::get('tree/{person}', [TreeController::class, 'show'])->name('tree.show');
    Route::put('tree/root-person/{person}', [TreeRootPersonController::class, 'update'])->name('tree.root-person.update');

    Route::resource('scans', ScanController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
    Route::get('scans/{scan}/file', [ScanController::class, 'file'])->name('scans.file');
    Route::get('scans/{scan}/thumbnail', [ScanController::class, 'thumbnail'])->name('scans.thumbnail');

    Route::post('events/{event}/scans', [EventScanController::class, 'store'])->name('events.scans.store');
    Route::delete('events/{event}/scans/{scan}', [EventScanController::class, 'destroy'])->name('events.scans.destroy');

    Route::resource('parent-child-relations', ParentChildRelationController::class)->only(['store', 'destroy']);

    Route::resource('marriages', MarriageController::class)->only(['store', 'update', 'destroy']);
});

require __DIR__.'/settings.php';
