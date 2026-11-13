<?php

use App\Http\Controllers\ProduitController;
use Illuminate\Support\Facades\Route;

// Aucune route de suppression : un produit se désactive, ses mouvements sont conservés.

Route::prefix('stock')->group(function () {
    Route::get('/', [ProduitController::class, 'index'])->name('produits.index');
    Route::get('/nouveau', [ProduitController::class, 'create'])->name('produits.create');
    Route::post('/', [ProduitController::class, 'store'])->name('produits.store');

    Route::get('/{produit}', [ProduitController::class, 'show'])->whereNumber('produit')->name('produits.show');
    Route::get('/{produit}/modifier', [ProduitController::class, 'edit'])->whereNumber('produit')->name('produits.edit');
    Route::put('/{produit}', [ProduitController::class, 'update'])->whereNumber('produit')->name('produits.update');
    Route::patch('/{produit}/statut', [ProduitController::class, 'toggle'])->whereNumber('produit')->name('produits.toggle');
    Route::post('/{produit}/mouvements', [ProduitController::class, 'mouvement'])->whereNumber('produit')->name('produits.mouvement');
});
