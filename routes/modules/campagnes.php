<?php

use App\Http\Controllers\CampagneController;
use Illuminate\Support\Facades\Route;

Route::prefix('campagnes')->group(function () {
    Route::get('/', [CampagneController::class, 'index'])->name('campagnes.index');
    Route::get('/nouvelle', [CampagneController::class, 'create'])->name('campagnes.create');
    Route::get('/apercu', [CampagneController::class, 'apercu'])->name('campagnes.apercu');
    Route::post('/', [CampagneController::class, 'store'])->name('campagnes.store');

    Route::get('/{campagne}', [CampagneController::class, 'show'])->whereNumber('campagne')->name('campagnes.show');
    Route::get('/{campagne}/modifier', [CampagneController::class, 'edit'])->whereNumber('campagne')->name('campagnes.edit');
    Route::put('/{campagne}', [CampagneController::class, 'update'])->whereNumber('campagne')->name('campagnes.update');
    Route::post('/{campagne}/lancer', [CampagneController::class, 'lancer'])->whereNumber('campagne')->name('campagnes.lancer');
    Route::post('/{campagne}/cloturer', [CampagneController::class, 'cloturer'])->whereNumber('campagne')->name('campagnes.cloturer');
    Route::post('/{campagne}/envoyer/{destinataire}', [CampagneController::class, 'envoyer'])
        ->whereNumber(['campagne', 'destinataire'])
        ->name('campagnes.envoyer');
});
