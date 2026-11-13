<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campagnes', function (Blueprint $table) {
            $table->id();
            $table->string('titre', 100);
            $table->text('message');
            $table->string('cible', 20);
            $table->unsignedSmallInteger('jours')->nullable();       // pour « absentes » et « nouvelles »
            $table->string('statut', 20)->default('brouillon');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('lancee_at')->nullable();
            $table->timestamps();

            $table->index('statut');
        });

        Schema::create('campagne_destinataires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campagne_id')->constrained('campagnes')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients');
            $table->timestamp('envoye_at')->nullable();
            $table->foreignId('envoye_par')->nullable()->constrained('users')->nullOnDelete();

            $table->unique(['campagne_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campagne_destinataires');
        Schema::dropIfExists('campagnes');
    }
};
