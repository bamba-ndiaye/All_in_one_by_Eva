<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produits', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->string('reference', 50)->nullable()->unique();
            $table->string('unite', 20)->default('pièce');
            $table->unsignedInteger('prix_achat')->nullable();       // FCFA, entier
            $table->unsignedInteger('prix_vente')->nullable();       // FCFA ; vide = non vendu aux clientes
            $table->unsignedInteger('seuil_alerte')->default(0);
            $table->integer('quantite')->default(0);                 // tenue à jour uniquement par les mouvements
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('nom');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produits');
    }
};
