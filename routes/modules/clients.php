<?php

use App\Http\Controllers\ClientController;
use Illuminate\Support\Facades\Route;

// Aucune route de suppression : un client s'archive, son historique est conservé.

Route::prefix('clients')->group(function () {
    Route::get('/', [ClientController::class, 'index'])->name('clients.index');
    Route::get('/nouveau', [ClientController::class, 'create'])->name('clients.create');
    Route::get('/export', [ClientController::class, 'export'])->name('clients.export');
    Route::post('/', [ClientController::class, 'store'])->name('clients.store');

    Route::get('/{client}', [ClientController::class, 'show'])->whereNumber('client')->name('clients.show');
    Route::get('/{client}/modifier', [ClientController::class, 'edit'])->whereNumber('client')->name('clients.edit');
    Route::put('/{client}', [ClientController::class, 'update'])->whereNumber('client')->name('clients.update');
    Route::patch('/{client}/statut', [ClientController::class, 'toggle'])->whereNumber('client')->name('clients.toggle');
});