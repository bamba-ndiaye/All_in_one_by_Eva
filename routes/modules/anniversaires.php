<?php

use App\Http\Controllers\AnniversaireController;
use Illuminate\Support\Facades\Route;

Route::prefix('anniversaires')->group(function () {
    Route::get('/', [AnniversaireController::class, 'index'])->name('anniversaires.index');
    Route::post('/{client}/souhaiter', [AnniversaireController::class, 'souhaiter'])->whereNumber('client')->name('anniversaires.souhaiter');
});
