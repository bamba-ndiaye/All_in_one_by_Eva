<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients');
            $table->foreignId('rendez_vous_id')->nullable()->constrained('rendez_vous');
            $table->string('type', 20)->default('paiement');
            // Une annulation pointe vers le paiement qu'elle annule : une seule annulation par paiement.
            $table->foreignId('paiement_origine_id')->nullable()->unique()->constrained('paiements');
            $table->foreignId('mode_paiement_id')->constrained('modes_paiement');
            $table->integer('montant');                      // FCFA, signé
            $table->unsignedInteger('montant_total');        // total à payer au moment de l'opération
            $table->integer('reste_apres')->default(0);      // reste à payer après cette opération
            $table->date('date');
            $table->string('reference', 100)->nullable();    // référence Wave, Orange Money, carte…
            $table->string('libelle', 150)->nullable();      // vente sans rendez-vous
            $table->string('motif', 255)->nullable();        // motif d'une annulation
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('date');
            $table->index('type');
            $table->index(['rendez_vous_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};