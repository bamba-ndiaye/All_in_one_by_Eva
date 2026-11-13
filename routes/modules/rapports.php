<?php

use App\Http\Controllers\RapportController;
use Illuminate\Support\Facades\Route;

Route::prefix('rapports')->group(function () {
    Route::get('/', [RapportController::class, 'index'])->name('rapports.index');
    Route::get('/export', [RapportController::class, 'export'])->name('rapports.export');
});
