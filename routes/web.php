<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [LoginController::class, 'create'])->name('login');
    Route::post('/connexion', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/deconnexion', [LoginController::class, 'destroy'])->name('logout');

    // Page provisoire, remplacée par le vrai tableau de bord au Sprint 4.
    Route::view('/', 'dashboard')->name('dashboard');

    // Un fichier de routes par module : routes/modules/*.php
    // Tout ce qui est dans ces fichiers est déjà protégé (connexion + compte actif).
    foreach (glob(base_path('routes/modules/*.php')) as $moduleRoutes) {
        require $moduleRoutes;
    }
});