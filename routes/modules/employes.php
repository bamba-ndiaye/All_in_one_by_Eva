<?php

use App\Http\Controllers\DisponibiliteController;
use App\Http\Controllers\EmployeController;
use Illuminate\Support\Facades\Route;

// Aucune route de suppression : un employé se désactive, son historique est conservé.

Route::prefix('equipe')->group(function () {
    Route::get('/', [EmployeController::class, 'index'])->name('employes.index');
    Route::get('/nouveau', [EmployeController::class, 'create'])->name('employes.create');
    Route::post('/', [EmployeController::class, 'store'])->name('employes.store');
    Route::get('/{employe}', [EmployeController::class, 'show'])->name('employes.show');
    Route::get('/{employe}/modifier', [EmployeController::class, 'edit'])->name('employes.edit');
    Route::put('/{employe}', [EmployeController::class, 'update'])->name('employes.update');
    Route::patch('/{employe}/statut', [EmployeController::class, 'toggle'])->name('employes.toggle');

    Route::get('/{employe}/disponibilites', [DisponibiliteController::class, 'edit'])->name('employes.disponibilites.edit');
    Route::put('/{employe}/disponibilites', [DisponibiliteController::class, 'update'])->name('employes.disponibilites.update');
});