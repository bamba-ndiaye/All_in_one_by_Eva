<?php

use App\Http\Controllers\ForfaitController;
use App\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;

// Aucune route de suppression : un service ou un forfait se désactive.

Route::prefix('prestations')->group(function () {
    Route::get('/', [ServiceController::class, 'index'])->name('services.index');
    Route::get('/nouvelle', [ServiceController::class, 'create'])->name('services.create');
    Route::post('/', [ServiceController::class, 'store'])->name('services.store');
    Route::get('/{service}/modifier', [ServiceController::class, 'edit'])->name('services.edit');
    Route::put('/{service}', [ServiceController::class, 'update'])->name('services.update');
    Route::patch('/{service}/statut', [ServiceController::class, 'toggle'])->name('services.toggle');
});

Route::prefix('forfaits')->group(function () {
    Route::get('/', [ForfaitController::class, 'index'])->name('services.forfaits.index');
    Route::get('/nouveau', [ForfaitController::class, 'create'])->name('services.forfaits.create');
    Route::post('/', [ForfaitController::class, 'store'])->name('services.forfaits.store');
    Route::get('/{forfait}/modifier', [ForfaitController::class, 'edit'])->name('services.forfaits.edit');
    Route::put('/{forfait}', [ForfaitController::class, 'update'])->name('services.forfaits.update');
    Route::patch('/{forfait}/statut', [ForfaitController::class, 'toggle'])->name('services.forfaits.toggle');
});