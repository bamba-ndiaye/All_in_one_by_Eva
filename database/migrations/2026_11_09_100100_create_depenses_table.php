<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categorie_depense_id')->constrained('categories_depenses');
            $table->unsignedInteger('montant');                        // FCFA, entier
            $table->date('date');
            $table->string('description', 255);
            $table->string('justificatif', 255)->nullable();           // chemin sur le disque privé
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('annulee_at')->nullable();
            $table->foreignId('annulee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motif_annulation', 255)->nullable();
            $table->timestamps();

            $table->index('date');
            $table->index(['categorie_depense_id', 'date']);
            $table->index('annulee_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depenses');
    }
};