<?php

use App\Http\Controllers\PaiementController;
use Illuminate\Support\Facades\Route;

Route::prefix('caisse')->group(function () {
    Route::get('/', [PaiementController::class, 'index'])->name('paiements.index');
    Route::get('/impayes', [PaiementController::class, 'impayes'])->name('paiements.impayes');
    Route::get('/nouveau', [PaiementController::class, 'create'])->name('paiements.create');
    Route::get('/clients', [PaiementController::class, 'clients'])->name('paiements.clients');
    Route::post('/', [PaiementController::class, 'store'])->name('paiements.store');

    Route::get('/{paiement}', [PaiementController::class, 'show'])->whereNumber('paiement')->name('paiements.show');
    Route::get('/{paiement}/recu', [PaiementController::class, 'recu'])->whereNumber('paiement')->name('paiements.recu');
    Route::post('/{paiement}/annuler', [PaiementController::class, 'annuler'])->whereNumber('paiement')->name('paiements.annuler');
    Route::post('/{paiement}/corriger', [PaiementController::class, 'corriger'])->whereNumber('paiement')->name('paiements.corriger');
});