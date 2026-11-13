<?php

use App\Http\Controllers\ParametreController;
use Illuminate\Support\Facades\Route;

Route::get('/parametres', [ParametreController::class, 'edit'])->name('parametres.index');
Route::put('/parametres', [ParametreController::class, 'update'])->name('parametres.update');
