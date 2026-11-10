<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rendez_vous', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients');
            $table->foreignId('employe_id')->constrained('employes');
            $table->date('date');
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->string('zone', 20)->default('mixte');
            $table->string('statut', 20)->default('en_attente');
            $table->unsignedInteger('prix_prevu')->default(0);   // FCFA, entier
            $table->text('commentaire')->nullable();
            $table->string('motif_annulation', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employe_id', 'date', 'heure_debut']);
            $table->index(['date', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rendez_vous');
    }
};