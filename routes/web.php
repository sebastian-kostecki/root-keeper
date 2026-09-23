<?php

use App\Http\Controllers\PersonController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('persons', PersonController::class)->only(['index', 'create', 'store', 'show']);
});

require __DIR__.'/settings.php';
