<?php

use App\Http\Controllers\RendezVousController;
use Illuminate\Support\Facades\Route;

// Aucune route de suppression : un rendez-vous s'annule, son historique est conservé.

Route::prefix('rendez-vous')->group(function () {
    Route::get('/', [RendezVousController::class, 'index'])->name('rendez-vous.index');
    Route::get('/nouveau', [RendezVousController::class, 'create'])->name('rendez-vous.create');
    Route::post('/', [RendezVousController::class, 'store'])->name('rendez-vous.store');
    Route::get('/creneaux', [RendezVousController::class, 'creneaux'])->name('rendez-vous.creneaux');
    Route::get('/clients', [RendezVousController::class, 'clients'])->name('rendez-vous.clients');

    Route::get('/{rendezVous}', [RendezVousController::class, 'show'])->whereNumber('rendezVous')->name('rendez-vous.show');
    Route::get('/{rendezVous}/modifier', [RendezVousController::class, 'edit'])->whereNumber('rendezVous')->name('rendez-vous.edit');
    Route::put('/{rendezVous}', [RendezVousController::class, 'update'])->whereNumber('rendezVous')->name('rendez-vous.update');
    Route::patch('/{rendezVous}/statut', [RendezVousController::class, 'statut'])->whereNumber('rendezVous')->name('rendez-vous.statut');
});