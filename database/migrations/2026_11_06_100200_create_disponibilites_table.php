<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disponibilites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $table->unsignedTinyInteger('jour_semaine');   // 1 = lundi … 7 = dimanche
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->timestamps();

            $table->index(['employe_id', 'jour_semaine']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disponibilites');
    }
};