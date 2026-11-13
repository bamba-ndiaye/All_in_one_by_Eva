<?php

use App\Http\Controllers\FideliteController;
use App\Http\Controllers\RecompenseController;
use Illuminate\Support\Facades\Route;

// Aucune route de suppression : les points se corrigent par un nouveau mouvement, une récompense se désactive.

Route::prefix('fidelite')->group(function () {
    Route::get('/', [FideliteController::class, 'index'])->name('fidelite.index');

    Route::get('/recompenses', [RecompenseController::class, 'index'])->name('fidelite.recompenses.index');
    Route::get('/recompenses/nouvelle', [RecompenseController::class, 'create'])->name('fidelite.recompenses.create');
    Route::post('/recompenses', [RecompenseController::class, 'store'])->name('fidelite.recompenses.store');
    Route::get('/recompenses/{recompense}/modifier', [RecompenseController::class, 'edit'])->whereNumber('recompense')->name('fidelite.recompenses.edit');
    Route::put('/recompenses/{recompense}', [RecompenseController::class, 'update'])->whereNumber('recompense')->name('fidelite.recompenses.update');
    Route::patch('/recompenses/{recompense}/statut', [RecompenseController::class, 'toggle'])->whereNumber('recompense')->name('fidelite.recompenses.toggle');

    Route::get('/clients/{client}', [FideliteController::class, 'client'])->whereNumber('client')->name('fidelite.client');
    Route::post('/clients/{client}/utiliser', [FideliteController::class, 'utiliser'])->whereNumber('client')->name('fidelite.utiliser');
    Route::post('/clients/{client}/corriger', [FideliteController::class, 'corriger'])->whereNumber('client')->name('fidelite.corriger');
});
