<?php

use App\Http\Controllers\DepenseController;
use Illuminate\Support\Facades\Route;

Route::prefix('depenses')->group(function () {
    Route::get('/', [DepenseController::class, 'index'])->name('depenses.index');
    Route::get('/nouvelle', [DepenseController::class, 'create'])->name('depenses.create');
    Route::post('/', [DepenseController::class, 'store'])->name('depenses.store');

    Route::get('/{depense}', [DepenseController::class, 'show'])->whereNumber('depense')->name('depenses.show');
    Route::get('/{depense}/modifier', [DepenseController::class, 'edit'])->whereNumber('depense')->name('depenses.edit');
    Route::put('/{depense}', [DepenseController::class, 'update'])->whereNumber('depense')->name('depenses.update');
    Route::post('/{depense}/annuler', [DepenseController::class, 'annuler'])->whereNumber('depense')->name('depenses.annuler');
    Route::get('/{depense}/justificatif', [DepenseController::class, 'justificatif'])->whereNumber('depense')->name('depenses.justificatif');
});