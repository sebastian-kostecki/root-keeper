<?php

use App\Http\Controllers\EventSourceController;
use App\Http\Controllers\MarriageController;
use App\Http\Controllers\ParentChildRelationController;
use App\Http\Controllers\PersonChildController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PersonParentController;
use App\Http\Controllers\PersonSpouseController;
use App\Http\Controllers\SourceController;
use App\Http\Controllers\TreeController;
use App\Http\Controllers\TreeRootPersonController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('persons', PersonController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('tree', [TreeController::class, 'index'])->name('tree.index');
    Route::get('tree/{person}', [TreeController::class, 'show'])->name('tree.show');
    Route::put('tree/root-person/{person}', [TreeRootPersonController::class, 'update'])->name('tree.root-person.update');

    Route::resource('sources', SourceController::class)->only(['index', 'create', 'store', 'show', 'update', 'destroy']);
    Route::get('sources/{source}/file', [SourceController::class, 'file'])->name('sources.file');
    Route::get('sources/{source}/thumbnail', [SourceController::class, 'thumbnail'])->name('sources.thumbnail');

    Route::post('events/{event}/sources', [EventSourceController::class, 'store'])->name('events.sources.store');
    Route::delete('events/{event}/sources/{source}', [EventSourceController::class, 'destroy'])->name('events.sources.destroy');

    Route::post('persons/{person}/parents', [PersonParentController::class, 'store'])->name('persons.parents.store');
    Route::post('persons/{person}/children/attach', [PersonChildController::class, 'attach'])->name('persons.children.attach');
    Route::post('persons/{person}/children', [PersonChildController::class, 'store'])->name('persons.children.store');
    Route::post('persons/{person}/spouses/attach', [PersonSpouseController::class, 'attach'])->name('persons.spouses.attach');
    Route::post('persons/{person}/spouses', [PersonSpouseController::class, 'store'])->name('persons.spouses.store');

    Route::resource('parent-child-relations', ParentChildRelationController::class)->only(['store', 'destroy']);

    Route::resource('marriages', MarriageController::class)->only(['store', 'update', 'destroy']);
});

require __DIR__.'/settings.php';
