<?php

use App\Http\Controllers\RechercheController;
use Illuminate\Support\Facades\Route;

Route::get('/recherche/clients', [RechercheController::class, 'clients'])->name('recherche.clients');
