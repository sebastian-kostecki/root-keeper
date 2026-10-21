<?php

use App\Http\Controllers\CemeteryController;
use App\Http\Controllers\CemeteryMergeController;
use App\Http\Controllers\EventSourceController;
use App\Http\Controllers\InlineCemeteryController;
use App\Http\Controllers\InlineLocalityController;
use App\Http\Controllers\InlineParishController;
use App\Http\Controllers\LocalityController;
use App\Http\Controllers\LocalityMergeController;
use App\Http\Controllers\MarriageController;
use App\Http\Controllers\ParentChildRelationController;
use App\Http\Controllers\ParishController;
use App\Http\Controllers\ParishMergeController;
use App\Http\Controllers\PersonChildController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PersonNoteController;
use App\Http\Controllers\PersonParentController;
use App\Http\Controllers\PersonPhotoController;
use App\Http\Controllers\PersonSpouseController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\SidePanelDemoController;
use App\Http\Controllers\SourceController;
use App\Http\Controllers\SourceLegacyEntryNumberController;
use App\Http\Controllers\TreeAncestorsController;
use App\Http\Controllers\TreeController;
use App\Http\Controllers\TreeProbandController;
use App\Http\Controllers\TreeRootPersonController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('persons', PersonController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('tree', [TreeController::class, 'index'])->name('tree.index');
    Route::get('tree/{person}', [TreeController::class, 'show'])->name('tree.show');
    Route::get('tree/{person}/ancestors', TreeAncestorsController::class)->name('tree.ancestors');
    Route::post('tree/proband/attach', [TreeProbandController::class, 'attach'])->name('tree.proband.attach');
    Route::post('tree/proband', [TreeProbandController::class, 'store'])->name('tree.proband.store');
    Route::put('tree/root-person/{person}', [TreeRootPersonController::class, 'update'])->name('tree.root-person.update');

    Route::resource('sources', SourceController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::get('sources/{source}/file', [SourceController::class, 'file'])->name('sources.file');
    Route::delete('sources/{source}/legacy-entry-number', SourceLegacyEntryNumberController::class)->name('sources.legacy-entry-number.destroy');
    Route::get('sources/{source}/thumbnail', [SourceController::class, 'thumbnail'])->name('sources.thumbnail');

    Route::get('places', [PlaceController::class, 'index'])->name('places.index');

    Route::post('localities/inline', [InlineLocalityController::class, 'store'])->name('localities.inline.store');
    Route::resource('localities', LocalityController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('localities/{locality}/merge', LocalityMergeController::class)->name('localities.merge');

    Route::post('parishes/inline', [InlineParishController::class, 'store'])->name('parishes.inline.store');
    Route::resource('parishes', ParishController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('parishes/{parish}/merge', ParishMergeController::class)->name('parishes.merge');

    Route::post('cemeteries/inline', [InlineCemeteryController::class, 'store'])->name('cemeteries.inline.store');
    Route::resource('cemeteries', CemeteryController::class)->only(['store', 'update', 'destroy']);
    Route::post('cemeteries/{cemetery}/merge', CemeteryMergeController::class)->name('cemeteries.merge');

    Route::post('events/{event}/sources', [EventSourceController::class, 'store'])->name('events.sources.store');
    Route::patch('events/{event}/sources/{source}', [EventSourceController::class, 'update'])->name('events.sources.update');
    Route::delete('events/{event}/sources/{source}', [EventSourceController::class, 'destroy'])->name('events.sources.destroy');

    Route::post('persons/{person}/parents', [PersonParentController::class, 'store'])->name('persons.parents.store');
    Route::post('persons/{person}/children/attach', [PersonChildController::class, 'attach'])->name('persons.children.attach');
    Route::post('persons/{person}/children', [PersonChildController::class, 'store'])->name('persons.children.store');
    Route::post('persons/{person}/spouses/attach', [PersonSpouseController::class, 'attach'])->name('persons.spouses.attach');
    Route::post('persons/{person}/spouses', [PersonSpouseController::class, 'store'])->name('persons.spouses.store');
    Route::put('persons/{person}/note', [PersonNoteController::class, 'update'])->name('persons.note.update');
    Route::post('persons/{person}/photo', [PersonPhotoController::class, 'store'])->name('persons.photo.store');
    Route::delete('persons/{person}/photo', [PersonPhotoController::class, 'destroy'])->name('persons.photo.destroy');
    Route::get('persons/{person}/photo', [PersonPhotoController::class, 'show'])->name('persons.photo.show');

    Route::resource('parent-child-relations', ParentChildRelationController::class)->only(['store', 'destroy']);

    Route::resource('marriages', MarriageController::class)->only(['store', 'update', 'destroy']);

    Route::get('dev/side-panel', SidePanelDemoController::class)->name('dev.side-panel');
});

require __DIR__.'/settings.php';
