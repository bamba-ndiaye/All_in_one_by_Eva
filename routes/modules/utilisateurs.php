<?php

use App\Http\Controllers\UtilisateurController;
use Illuminate\Support\Facades\Route;

// Aucune route de suppression : un compte se désactive, son journal d'actions est conservé.

Route::prefix('utilisateurs')->group(function () {
    Route::get('/', [UtilisateurController::class, 'index'])->name('utilisateurs.index');
    Route::get('/nouveau', [UtilisateurController::class, 'create'])->name('utilisateurs.create');
    Route::post('/', [UtilisateurController::class, 'store'])->name('utilisateurs.store');

    Route::get('/{utilisateur}', [UtilisateurController::class, 'show'])->whereNumber('utilisateur')->name('utilisateurs.show');
    Route::get('/{utilisateur}/modifier', [UtilisateurController::class, 'edit'])->whereNumber('utilisateur')->name('utilisateurs.edit');
    Route::put('/{utilisateur}', [UtilisateurController::class, 'update'])->whereNumber('utilisateur')->name('utilisateurs.update');
    Route::patch('/{utilisateur}/statut', [UtilisateurController::class, 'toggle'])->whereNumber('utilisateur')->name('utilisateurs.toggle');
});
